<?php
namespace RestOnPhp;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Loader\YamlFileLoader as LoaderYamlFileLoader;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

abstract class Kernel implements HttpKernelInterface {
    /**
     * @var array $routes
     */
    private $routes;

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerBuilder $dependencyContainer
     */
    private $dependencyContainer;

    public function __construct() {
        $cache_dir = $this->getCacheDir();
        $config_dir = $this->getConfigDir();

        if(file_exists($cache_dir . '/CompiledDependencyContainer.php')) {
            require_once $cache_dir . '/CompiledDependencyContainer.php';
            $this->dependencyContainer = new \CompiledDependencyContainer();
        } else {
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

        if(!file_exists($cache_dir . '/routes.php')) {
            $route_loader = new LoaderYamlFileLoader(new FileLocator($config_dir));
            $this->routes = $route_loader->load('routing.yml');
            $this->routes = (new CompiledUrlMatcherDumper($this->routes))->getCompiledRoutes();
            file_put_contents($cache_dir . '/routes.php', sprintf("<?php\nreturn %s;\n", var_export($this->routes, true)));
        } else {
            $this->routes = require_once $cache_dir . '/routes.php';
        }

    }

    public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true) {
        $context = new RequestContext();
        $context->fromRequest($request);
        
        $matcher = new CompiledUrlMatcher($this->routes, $context);

        try {
            $attributes = $matcher->match($request->getPathInfo());

            if(isset($attributes['secure']) && $attributes['secure']) {
                try {
                    $user = $this->dependencyContainer->get('api.handler.auth')->verify(isset($_COOKIE['token']) ? $_COOKIE['token'] : null);
                    $this->dependencyContainer->get('api.session.storage')->setUser($user);
                } catch(UnauthorizedHttpException $e) {
                    return new Response(json_encode($e->getMessage()), $e->getStatusCode(), ['Content-Type' => 'application/json']);
                }
            }

            if(!isset($attributes['resource'])) {
                $handler = $this->dependencyContainer->get($attributes['_controller']);
                $response = $handler->handle($request);
            } else {
                $single_form = substr(ucfirst($attributes['resource']), 0, -1);
                $entityClass = sprintf('%s\\%s', $this->dependencyContainer->getParameter('entity_namespace'), Utils::camelize($single_form));

                if(!class_exists($entityClass)) {
                    $response = new Response(json_encode('Not found!'), Response::HTTP_NOT_FOUND, ['Content-Type' => 'application/json']);
                } else if(isset($attributes['_controller'])) {
                    $id = isset($attributes['id']) ? $attributes['id'] : null;
                    $handler = $this->dependencyContainer->get($attributes['_controller']);
                    $response = $handler->handle($entityClass, $request, $id);
                }
            }
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
        $this->routes->add($path, new Route($path, array('callback' => $callback)));
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
