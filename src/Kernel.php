<?php
namespace RestOnPhp;

use Exception;
use RestOnPhp\Metadata\XmlMetadata;
use RestOnPhp\Event\PreNormalizeEvent;
use RestOnPhp\Event\PreSerializeEvent;
use RestOnPhp\Event\PostNormalizeEvent;
use RestOnPhp\Event\PostSerializeEvent;
use RestOnPhp\Normalizer\RootNormalizer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use RestOnPhp\DependencyInjection\Compiler\EventPass;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use RestOnPhp\DependencyInjection\Compiler\LoggerPass;
use RestOnPhp\Handler\Response\HandlerResponseInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\Validator\Exception\ValidatorException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Loader\XmlFileLoader as RoutingXmlFileLoader;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

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
        $this->loadDependencyContainer();
        $this->loadRoutes();

        $this->eventDispatcher = $this->dependencyContainer->get('api.event.dispatcher');
        $this->logger = $this->dependencyContainer->get('api.logger');
        $this->metadata = $this->dependencyContainer->get('api.metadata.xml');
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
        ini_set('error_log', $log_dir . '/php_error.log');
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

        if(!file_exists($cache_dir . '/routes.php')) {
            /**
             * @var XmlMetadata
             */
            $metadata = $this->dependencyContainer->get('api.metadata.xml');
            $routes = $metadata->getRouteMetadata();

            $routeCollection = new RouteCollection();

            foreach($routes as $route) {
                $routeCollection->add($route['name'], new Route($route['path'], [
                    'resource' => $route['resource'],
                    'handler' => $route['handler'],
                    'method' => $route['method']
                ], [], [], '', [], [ $route['http_method'] ], ''));
            }

            $this->routes = (new CompiledUrlMatcherDumper($routeCollection))->getCompiledRoutes();
            file_put_contents($cache_dir . '/routes.php', sprintf("<?php\nreturn %s;\n", var_export($this->routes, true)));
        } else {
            $this->routes = require_once $cache_dir . '/routes.php';
        }
    }

    private function normalize($resource_name, HandlerResponseInterface $handler_response) {
        $this->logger->info('NORMALIZE', [
            'resource_name' => $resource_name
        ]);

        $resource_metadata = $this->metadata->getMetadataFor($resource_name);

        if($resource_metadata['normalizer']) {
            $normalizer = $this->dependencyContainer->get($resource_metadata['normalizer']);
        } else {
            /**
             * @var RootNormalizer
             */
            $normalizer = $this->dependencyContainer->get('api.normalizer');
        }

        if($handler_response->getCardinality() == HandlerResponseInterface::CARDINALITY_COLLECTION) {
            $normalized = ['items' => $normalizer->normalizeCollection($handler_response->getData(), $resource_metadata) ];
            $normalized['pagination'] = $handler_response->getPagination();
        } else if($handler_response->getCardinality() == HandlerResponseInterface::CARDINALITY_SINGLE) {
            $normalized = $normalizer->normalizeItem($handler_response->getData(), $resource_metadata);
        } else {
            $normalized = $handler_response->getData();
        }

        return $normalized;
    }

    private function serialize($data) {
        $this->logger->info('SERIALIZE');
        return json_encode($data);
    }

    private function getHandler() {
        $this->logger->info('HANDLER_LOAD');

        $matcher = new CompiledUrlMatcher($this->routes, $this->context);
        $attributes = $matcher->match($this->request->getPathInfo());
        $parameters = [];
        $entityClass = null;
        $handler_id = isset($attributes['handler']) ? $attributes['handler'] : '';
        $resource_metadata = $this->metadata->getMetadataFor($attributes['resource']);
        $entityClass = $resource_metadata['entity'];

        if(!class_exists($entityClass)) {
            $this->logger->error('HANDLER_LOAD_FAIL', [
                'message' => sprintf('%s resource class does not exist!', $entityClass),
                'attributes' => $attributes
            ]);

            throw new ResourceNotFoundException(sprintf('%s resource class does not exist!', $entityClass));
        }

        try {
            $handler = $this->dependencyContainer->get($handler_id);
        } catch(Exception $e) {
            throw new NoConfigurationException(sprintf('Resource has not handler assigned %s', $attributes['resource']));
        }

        $method = 'handle';
        
        if(isset($attributes['method'])) {
            $method = $attributes['method'];
        }
        

        $reflectionClass = new \ReflectionClass($handler);
        $reflectionMethod = $reflectionClass->getMethod($method);
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

    /**
     * @param Request $request
     * @param int $type  The type of the request (one of HttpKernelInterface::MAIN_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param bool $catch Whether to catch exceptions or not
     * @return Response
     * @throws \Exception When an Exception occurs during processing
     */
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true) {
        if(Request::METHOD_OPTIONS == $request->getMethod()) {
            return new Response('', 200);
        }

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

            /**
             * @var HandlerResponseInterface
             */
            $handler_response = $reflectionMethod->invokeArgs($handler, $args);

            if($handler_response->getData() instanceof Response) {
                return $handler_response->getData();
            }

            $this->eventDispatcher->dispatch(new PreNormalizeEvent($resource_name, $handler_response), PreNormalizeEvent::NAME);
            $normalized = $this->normalize($resource_name, $handler_response);
            $this->eventDispatcher->dispatch(new PostNormalizeEvent($resource_name, $handler_response, $normalized), PostNormalizeEvent::NAME);

            $this->eventDispatcher->dispatch(new PreSerializeEvent($resource_name, $handler_response, $normalized), PreSerializeEvent::NAME);
            $serialized = $this->serialize($normalized);
            $this->eventDispatcher->dispatch(new PostSerializeEvent($resource_name, $handler_response, $normalized, $serialized), PostSerializeEvent::NAME);

            $response = new Response($serialized, 200, [ 'Content-Type' => 'application/json' ]);
        } catch(ValidatorException $e) {
            $response = new Response(json_encode([ 
                'message' => 'Invalid input',
                'exception_message' => $e->getMessage()
            ], 'json'), 400);
        } catch(UniqueConstraintViolationException $e) {
            $response = new Response(
                json_encode([
                    'message' => 'Item already exists',
                    'exception_message' => $e->getMessage()
                ]), 409, ['Content-Type' => 'application/json']
            );
        } catch(NoConfigurationException $e) { 
            $response = new Response(json_encode([
                'message' => 'Not found!',
                'exception_message' => $e->getMessage()
            ]), Response::HTTP_NOT_FOUND, ['Content-Type' => 'application/json']);
        } catch (ResourceNotFoundException $e) {
		    $response = new Response(json_encode([
                'message' => 'Not found!',
                'exception_message' => $e->getMessage()
            ]), Response::HTTP_NOT_FOUND, ['Content-Type' => 'application/json']);
		} catch (MethodNotAllowedException $e) {
            $response = new Response(json_encode([
                'message' => 'Not allowed!',
                'exception_message' => $e->getMessage()
            ]), Response::HTTP_METHOD_NOT_ALLOWED, ['Content-Type' => 'application/json']);
        } catch(HttpException $e) {
            $response = new Response(json_encode([
                'message' => 'HTTP exception',
                'exception_message' => $e->getMessage()
            ]), $e->getStatusCode(), ['Content-Type' => 'application/json']);
        } catch(NotEncodableValueException $e) {
            $response = new Response(json_encode([
                'message' => 'Not encodable',
                'exception_message' => $e->getMessage()
            ]), 400, ['Content-Type' => 'application/json']);
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
        return __DIR__ . '/../../..';
    }
}
