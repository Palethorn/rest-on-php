<?php
namespace RestOnPhp;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use RestOnPhp\DependencyInjection\Compiler\LoggerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Loader\YamlFileLoader as LoaderYamlFileLoader;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Exception\ValidatorException;

abstract class Kernel implements HttpKernelInterface {
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

    public function __construct() {
        $this->checkDirs();
        $this->loadRoutes();
        $this->loadDependencyContainer();

        $this->logger = $this->dependencyContainer->get('api.logger');
        $this->metadata = $this->dependencyContainer->get('api.metadata.xml');
        $this->serializer = $this->dependencyContainer->get('symfony.serializer');
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
        $cache_dir = $this->getCacheDir();
        $log_dir = $this->getLogDir();
        $config_dir = $this->getConfigDir();

        if(file_exists($cache_dir . '/CompiledDependencyContainer.php')) {
            require_once $cache_dir . '/CompiledDependencyContainer.php';
            $this->dependencyContainer = new \CompiledDependencyContainer();
            return;
        }

        $this->dependencyContainer = new ContainerBuilder();
        $this->dependencyContainer->addCompilerPass(new LoggerPass());
        $loader = new YamlFileLoader($this->dependencyContainer, new FileLocator($config_dir));
        $loader->load('services.yml');
        $this->dependencyContainer->setParameter('config_dir', $config_dir);
        $this->dependencyContainer->setParameter('cache_dir', $cache_dir);
        $this->dependencyContainer->setParameter('log_dir', $log_dir);
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
            $route_loader = new LoaderYamlFileLoader(new FileLocator($config_dir));
            $this->routes = $route_loader->load('routing.yml');
            $this->routes = (new CompiledUrlMatcherDumper($this->routes))->getCompiledRoutes();
            file_put_contents($cache_dir . '/routes.php', sprintf("<?php\nreturn %s;\n", var_export($this->routes, true)));
        } else {
            $this->routes = require_once $cache_dir . '/routes.php';
        }
    }

    private function normalize($entityClass, $data) {
        $this->logger->info('NORMALIZE', [
            'entity_class' => $entityClass
        ]);

        $fields = $this->metadata->getNormalizerFieldsFor($entityClass);
        
        if($data[0] == 'collection') {
            $normalized = array('items' => $this->serializer->normalize(
                $data[1], 
                null,
                [AbstractNormalizer::ATTRIBUTES => $fields]
            ));

            $normalized['pagination'] = $data[2];

        } else if($data[0] == 'item') {
            $fields = $this->metadata->getNormalizerFieldsFor($entityClass);
            $normalized = $this->serializer->normalize(
                $data[1], 
                null,
                [AbstractNormalizer::ATTRIBUTES => $fields]
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

        if(!isset($attributes['_controller'])) {
            $this->logger->error('HANDLER_LOAD_FAIL', [
                'message' => 'Missing controller property on route',
                'attributes' => $attributes
            ]);

            throw new NoConfigurationException(sprintf('Missing controller property on route'));
        }

        if(!isset($attributes['resource'])) {
            $handler = $this->dependencyContainer->get($attributes['_controller']);
            $reflectionClass = new \ReflectionClass($handler);
            $reflectionMethod = $reflectionClass->getMethod('handle');

            foreach($reflectionMethod->getParameters() as $parameter) {
                if(isset($attributes[$parameter->name])) {
                    $parameters[] = $attributes[$parameter->name];
                }
            }

            return [ $handler, $reflectionMethod, $parameters];
        }

        $single_form = substr(ucfirst($attributes['resource']), 0, -1);
        $entityClass = sprintf('%s\\%s', $this->dependencyContainer->getParameter('entity_namespace'), Utils::camelize($single_form));

        if(!class_exists($entityClass)) {
            $this->logger->error('HANDLER_LOAD_FAIL', [
                'message' => sprintf('%s resource class does not exist!', $entityClass),
                'attributes' => $attributes
            ]);

            throw new ResourceNotFoundException(sprintf('%s resource class does not exist!', $entityClass));
        }

        $handler = $this->dependencyContainer->get($attributes['_controller']);
        $reflectionClass = new \ReflectionClass($handler);
        $reflectionMethod = $reflectionClass->getMethod('handle');
        $parameters[] = $entityClass;

        foreach($reflectionMethod->getParameters() as $parameter) {
            if(isset($attributes[$parameter->name])) {
                $parameters[] = $attributes[$parameter->name];
            }
        }

        return [ $handler, $reflectionMethod, $parameters];
    }

    private function security($entityClass) { 
        $this->logger->info('SECURITY_CHECK');
        /**
         * @var Security\Authorization
         */
        $authorization = $this->dependencyContainer->get('api.security.authorization');
        $authorization->authorize($entityClass);
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
            $entityClass = isset($args[0]) ? $args[0] : null;
            $this->security($entityClass);
            $data = $reflectionMethod->invokeArgs($handler, $args);

            if($data instanceof Response) {
                return $data;
            }

            $normalized = $this->normalize($entityClass, $data);
            $serialized = $this->serialize($normalized);
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

    abstract function getProjectDir();
}
