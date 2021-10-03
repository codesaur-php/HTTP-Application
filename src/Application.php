<?php

namespace codesaur\Http\Application;

use Closure;
use Error;
use BadMethodCallException;
use InvalidArgumentException;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Router\Route;
use codesaur\Router\Router;
use codesaur\Router\RouterInterface;
use codesaur\Http\Message\Response;

class Application implements RequestHandlerInterface
{
    protected $router;
    
    private $_middlewares = array();
            
    function __construct()
    {
        $this->router = new Router();
    }

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array(array($this->router, $name), $arguments);
    }
    
    public function use($object)
    {
        if ($object instanceof MiddlewareInterface
                || $object instanceof Closure) {
            $this->_middlewares[] = $object;
        } elseif($object instanceof RouterInterface) {
            $this->router->merge($object);
        } elseif ($object instanceof ExceptionHandlerInterface) {
            return set_exception_handler(array($object, 'exception'));
        } else {
            throw new InvalidArgumentException();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $callbacks = $this->_middlewares;
        $callbacks[] = function($request) {
            $uri_path = rawurldecode($request->getUri()->getPath());
            $script_path = rtrim(dirname($request->getServerParams()['SCRIPT_NAME']), '/') ;
            $target_path = str_replace($script_path . $request->getAttribute('pipe'), '', $uri_path);
            $route = $this->router->match($target_path, $request->getMethod());
            if (!$route instanceof Route) {
                $pattern = rawurldecode($target_path);
                throw new Error("Unknown route pattern [$pattern]", 404);
            }            

            $params = array();
            foreach ($route->getParameters() as $param => $value) {
               $params[$param] = $value;
            }
            $request = $request->withAttribute('params', $params);
            $request = $request->withAttribute('router', $this->router);
            
            $callback = $route->getCallback();
            if ($callback instanceof Closure) {
                $response = call_user_func_array($callback, array($request));
            } else {
                $controllerClass = $callback[0];
                if (!class_exists($controllerClass)) {
                    throw new Error("$controllerClass is not available", 501);
                }

                $action = $callback[1] ?? 'index';
                $controller = new $controllerClass($request);
                if (!method_exists($controller, $action)) {
                    throw new BadMethodCallException(__CLASS__ . ": Action named $action is not part of $controllerClass");
                }
                $response = call_user_func_array(array($controller, $action), $route->getParameters());
            }

            return $response instanceof ResponseInterface ? $response : new Response();
        };
        
        reset($callbacks);
        $runner = new class ($callbacks) implements RequestHandlerInterface
        {
            public function __construct($queue)
            {
                $this->queue = $queue;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $current = current($this->queue);
                next($this->queue);
                
                if ($current instanceof MiddlewareInterface) {
                    return $current->process($request, $this);
                } elseif ($current instanceof Closure) {
                    return call_user_func_array($current, array($request, $this));
                }
                
                return $current($request);
            }
        };
        
        return $runner->handle($request);
    }
}
