<?php
namespace RestOnPhp;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use RestOnPhp\DependencyInjection\Compiler\EventPass;
use RestOnPhp\DependencyInjection\Compiler\LoggerPass;
use RestOnPhp\Event\PostNormalizeEvent;
use RestOnPhp\Event\PostSerializeEvent;
use RestOnPhp\Event\PreNormalizeEvent;
use RestOnPhp\Event\PreSerializeEvent;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Loader\XmlFileLoader as RoutingXmlFileLoader;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Exception\ValidatorException;

class Kernel implements HttpKernelInterface {
    /**
     * @var array $routes
     */
    private $routes;

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerBuilder $dependencyContainer
     */
    private $dependencyContainer;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $request;

    /**
     * @var \RestOnPhp\Metadata\XmlMetadata
     */
    private $metadata;

    /**
     * @var \Symfony\Component\Routing\RequestContext
     */
    private $context;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * @var string
     */
    private $env;

    /**
     * @var boolean
     */
    private $debug;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    public function __construct($env, $debug) {
        $this->env = $env;
        $this->debug = $debug;

        $this->checkDirs();
        $this->loadRoutes();
        $this->loadDependencyContainer();

        $this->eventDispatcher = $this->dependencyContainer->get('api.event.dispatcher');
        $this->logger = $this->dependencyContainer->get('api.logger');
        $this->metadata = $this->dependencyContainer->get('api.metadata.xml');
        $this->serializer = $this->dependencyContainer->get('api.serializer');
    }

    public function checkDirs() {
        if(!is_dir($this->getCacheDir())) {
            mkdir($this->getCacheDir(), 0777, true);
        }

        if(!is_dir($this->getLogDir())) {
            mkdir($this->getLogDir(), 0777, true);
        }
    }

    private function loadDependencyContainer() {
        $project_dir = $this->getProjectDir();
        $cache_dir = $this->getCacheDir();
        $log_dir = $this->getLogDir();
        $config_dir = $this->getConfigDir();
        $public_dir = $this->getPublicDir();

        if($this->env != 'cli' && file_exists($cache_dir . '/CompiledDependencyContainer.php')) {
            require_once $cache_dir . '/CompiledDependencyContainer.php';
            $this->dependencyContainer = new \CompiledDependencyContainer();
            return;
        }

        $this->dependencyContainer = new ContainerBuilder();
        $this->dependencyContainer->setParameter('project_dir', $project_dir);
        $this->dependencyContainer->setParameter('public_dir', $public_dir);
        $this->dependencyContainer->setParameter('config_dir', $config_dir);
        $this->dependencyContainer->setParameter('cache_dir', $cache_dir);
        $this->dependencyContainer->setParameter('log_dir', $log_dir);
        $this->dependencyContainer->addCompilerPass(new EventPass());
        $this->dependencyContainer->addCompilerPass(new LoggerPass());
        $loader = new XmlFileLoader($this->dependencyContainer, new FileLocator($config_dir));
        $loader->load('services.xml');
        $this->dependencyContainer->compile();
        $dumper = new PhpDumper($this->dependencyContainer);

        if(!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }

        file_put_contents(
            $cache_dir . '/CompiledDependencyContainer.php',
            $dumper->dump(['class' => 'CompiledDependencyContainer'])
        );
    }

    private function loadRoutes() {
        $cache_dir = $this->getCacheDir();
        $config_dir = $this->getConfigDir();

        if(!file_exists($cache_dir . '/routes.php')) {
            $route_loader = new RoutingXmlFileLoader(new FileLocator($config_dir));
            $this->routes = $route_loader->load('routing.xml');
            $this->routes = (new CompiledUrlMatcherDumper($this->routes))->getCompiledRoutes();
            file_put_contents($cache_dir . '/routes.php', sprintf("<?php\nreturn %s;\n", var_export($this->routes, true)));
        } else {
            $this->routes = require_once $cache_dir . '/routes.php';
        }
    }

    private function normalize($resource_name, $data) {
        $this->logger->info('NORMALIZE', [
            'resource_name' => $resource_name
        ]);

        $resource_metadata = $this->metadata->getMetadataFor($resource_name);
        $fields = $this->metadata->getNormalizerFieldsFor($resource_name);
        
        if($data[0] == 'collection') {
            $normalized = array('items' => $this->serializer->normalize(
                $data[1], 
                null,
                [
                    AbstractNormalizer::ATTRIBUTES => $fields,
                    'resource_metadata' => $resource_metadata
                ]
            ));

            $normalized['pagination'] = $data[2];

        } else if($data[0] == 'item') {
            $fields = $this->metadata->getNormalizerFieldsFor($resource_name);
            $normalized = $this->serializer->normalize(
                $data[1], 
                null,
                [
                    AbstractNormalizer::ATTRIBUTES => $fields,
                    'resource_metadata' => $resource_metadata
                ]
            );
        } else {
            $normalized = $data;
        }

        return $normalized;
    }

    private function serialize($data) {
        $this->logger->info('SERIALIZE');
        return $this->serializer->serialize($data, 'json');
    }

    private function getHandler() {
        $this->logger->info('HANDLER_LOAD');

        $matcher = new CompiledUrlMatcher($this->routes, $this->context);
        $attributes = $matcher->match($this->request->getPathInfo());
        $parameters = [];

        $entityClass = null;
        $handler_id = $attributes['_controller'];
        $resource_metadata = $this->metadata->getMetadataFor($attributes['resource']);
        $entityClass = $resource_metadata['entity'];

        if($resource_metadata['handler']) {
            $handler_id = $resource_metadata['handler'];
        }

        if(!class_exists($entityClass)) {
            $this->logger->error('HANDLER_LOAD_FAIL', [
                'message' => sprintf('%s resource class does not exist!', $entityClass),
                'attributes' => $attributes
            ]);

            throw new ResourceNotFoundException(sprintf('%s resource class does not exist!', $entityClass));
        }

        $handler = $this->dependencyContainer->get($handler_id);
        $reflectionClass = new \ReflectionClass($handler);
        $reflectionMethod = $reflectionClass->getMethod('handle');
        $parameters[] = $attributes['resource'];

        foreach($reflectionMethod->getParameters() as $parameter) {
            if(isset($attributes[$parameter->name])) {
                $parameters[] = $attributes[$parameter->name];
            }
        }

        $parameters[] = $entityClass;
        $parameters[] = $resource_metadata;

        return [ $handler, $reflectionMethod, $parameters];
    }

    private function security($resource_name) {
        $this->logger->info('SECURITY_CHECK');
        /**
         * @var Security\Authorization
         */
        $authorization = $this->dependencyContainer->get('api.security.authorization');
        $authorization->authorize($resource_name);
    }

    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true) {

        $this->logger->info('REQUEST', [
            'client_ip' => $request->getClientIp(),
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'query_string' => $request->getQueryString(),
            'content' => $request->getContent()
        ]);

        $this->request = $request;
        $request_stack = $this->dependencyContainer->get('api.request.stack');
        $request_stack->push($this->request);
        $this->context = new RequestContext();
        $this->context->fromRequest($request);

        try {
            list($handler, $reflectionMethod, $args) = $this->getHandler();
            $resource_name = isset($args[0]) ? $args[0] : null;
            $this->security($resource_name);
            $data = $reflectionMethod->invokeArgs($handler, $args);

            if($data instanceof Response) {
                return $data;
            }

            $this->eventDispatcher->dispatch(new PreNormalizeEvent($resource_name, $data), PreNormalizeEvent::NAME);
            $normalized = $this->normalize($resource_name, $data);
            $this->eventDispatcher->dispatch(new PostNormalizeEvent($resource_name, $data, $normalized), PostNormalizeEvent::NAME);

            $this->eventDispatcher->dispatch(new PreSerializeEvent($resource_name, $data, $normalized), PreSerializeEvent::NAME);
            $serialized = $this->serialize($normalized);
            $this->eventDispatcher->dispatch(new PostSerializeEvent($resource_name, $data, $normalized, $serialized), PostSerializeEvent::NAME);

            $response = new Response($serialized, 200, [ 'Content-Type' => 'application/json' ]);
        } catch(ValidatorException $e) {
            $response = new Response($this->serializer->serialize(array('message' => $e->getMessage()), 'json'), 400);
        } catch(UniqueConstraintViolationException $e) {
            $response = new Response(
                $this->serializer->serialize(array('message' => 'Item already exists'), 'json'), 409, ['Content-Type' => 'application/json']
            );
        } catch(NoConfigurationException $e) { 
            $response = new Response(json_encode('Not found! ' . $e->getMessage()), Response::HTTP_NOT_FOUND, ['Content-Type' => 'application/json']);
        } catch (ResourceNotFoundException $e) {
		    $response = new Response(json_encode('Not found! ' . $e->getMessage()), Response::HTTP_NOT_FOUND, ['Content-Type' => 'application/json']);
		} catch (MethodNotAllowedException $e) {
            $response = new Response(json_encode('Not allowed!'), Response::HTTP_METHOD_NOT_ALLOWED, ['Content-Type' => 'application/json']);
        } catch(HttpException $e) {
            $response = new Response(json_encode($e->getMessage()), $e->getStatusCode(), ['Content-Type' => 'application/json']);
        } catch(NotEncodableValueException $e) {
            $response = new Response(json_encode($e->getMessage()), 400, ['Content-Type' => 'application/json']);
        }

        $this->logger->info('RESPONSE', [
            'content_length' => strlen($response->getContent()),
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type')
        ]);

        return $response;
    }

    public function getDependencyContainer() {
        return $this->dependencyContainer;
    }

    public function getCacheDir() {
        return $this->getProjectDir() . '/cache';
    }

    public function getLogDir() {
        return $this->getProjectDir() . '/log';
    }

    public function getConfigDir() {
        return $this->getProjectDir() . '/config';
    }

    public function getPublicDir() {
        return $this->getProjectDir() . '/web';
    }

    public function getProjectDir() {
        return __DIR__ . '/../../../..';
    }
}
