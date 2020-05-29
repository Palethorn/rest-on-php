<?php
namespace RestOnPhp;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

    public function __construct() {
        $this->loadDependencyContainer();
        $this->loadRoutes();
        $this->metadata = $this->dependencyContainer->get('api.metadata.xml');
        $this->serializer = $this->dependencyContainer->get('symfony.serializer');
    }

    private function loadDependencyContainer() {
        $cache_dir = $this->getCacheDir();
        $config_dir = $this->getConfigDir();

        if(file_exists($cache_dir . '/CompiledDependencyContainer.php')) {
            require_once $cache_dir . '/CompiledDependencyContainer.php';
            $this->dependencyContainer = new \CompiledDependencyContainer();
            return;
        }

        $this->dependencyContainer = new ContainerBuilder();
        $loader = new YamlFileLoader($this->dependencyContainer, new FileLocator($config_dir));
        $loader->load('services.yml');
        $this->dependencyContainer->setParameter('config_dir', $config_dir);
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
        return $this->serializer->serialize($data, 'json');
    }

    private function getHandler() {
        $matcher = new CompiledUrlMatcher($this->routes, $this->context);
        $attributes = $matcher->match($this->request->getPathInfo());

        if(!isset($attributes['_controller'])) {
            throw new NoConfigurationException(sprintf('Missing controller property on route'));
        }

        if(!isset($attributes['resource'])) {
            $handler = $this->dependencyContainer->get($attributes['_controller']);
            return [ $handler, null, null];
        }

        $single_form = substr(ucfirst($attributes['resource']), 0, -1);
        $entityClass = sprintf('%s\\%s', $this->dependencyContainer->getParameter('entity_namespace'), Utils::camelize($single_form));

        if(!class_exists($entityClass)) {
            throw new ResourceNotFoundException(sprintf('%s resource class does not exist!', $entityClass));
        }

        $id = isset($attributes['id']) ? $attributes['id'] : null;
        $handler = $this->dependencyContainer->get($attributes['_controller']);
        return [ $handler, $entityClass, $id];
    }

    private function security($entityClass) { 
        try {
            $resourceMetadata = $this->metadata->getMetadataFor($entityClass);
        } catch(ResourceNotFoundException $e) {

            $resourceMetadata = [ 'secure' => false ];
        }

        if(isset($resourceMetadata['secure']) && $resourceMetadata['secure']) {
            $token_extractor = $this->dependencyContainer->get('api.token.extractor');
            $token = $token_extractor->extract($this->request);
            $user = $this->dependencyContainer->get('api.handler.auth')->verify($token);
            $this->dependencyContainer->get('api.session.storage')->setUser($user);
        }
    }

    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true) {
        $this->request = $request;
        $this->context = new RequestContext();
        $this->context->fromRequest($request);

        try {
            list($handler, $entityClass, $id) = $this->getHandler();
            $this->security($entityClass);
            $data = $handler->handle($entityClass, $request, $id);

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

        return $response;
    }

    public function addRoute($path, $callback) {
        $this->routes[$path] = [[[
                '_route' => $path,
                '_controller' => $callback,
            ],
            NULL, [
                'POST' => 0,
            ],
            NULL,
            false,
            false,
            NULL,
        ]];
    }

    public function getDependencyContainer() {
        return $this->dependencyContainer;
    }

    public function getCacheDir() {
        return $this->getProjectDir() . '/cache';
    }

    public function getConfigDir() {
        return $this->getProjectDir() . '/config';
    }

    abstract function getProjectDir();
}
