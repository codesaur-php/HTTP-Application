<?php

namespace codesaur\Http\Application;

use Closure;
use Error;
use BadMethodCallException;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Router\Router;
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
        if (isset($arguments[0])) {
            return call_user_func_array(array($this->router, $name), $arguments);
        }
        
        throw new BadMethodCallException(__CLASS__ . ": Bad method [$name] call");
    }
    
    public function use($object)
    {
        if ($object instanceof ExceptionHandlerInterface) {
            return set_exception_handler(array($object, 'exception'));
        } elseif ($object instanceof MiddlewareInterface) {
            $this->_middlewares[] = $object;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $callbacks = $this->_middlewares;
        $callbacks[] = function($request) {
            $script_path = dirname($request->getServerParams()['SCRIPT_NAME']);
            $uri_path = rawurldecode($request->getUri()->getPath());
            $target_path = str_replace($script_path, '', $uri_path);
            $route = $this->router->match($target_path, $request->getMethod());
            if (!isset($route)) {
                if (empty($target_path)) {
                    $target_path = '/';
                }
                throw new Error("Unknown route pattern [$target_path]", 404);
            }

            foreach ($route->getParameters() as $param => $value) {
                $request = $request->withAttribute($param, $value);
            }

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

            public function handle(ServerRequestInterface $request) : ResponseInterface
            {
                $current = current($this->queue);
                next($this->queue);
                
                if (is_callable($current)) {
                    return $current($request);
                }
                
                return $current->process($request, $this);
            }
        };
        
        return $runner->handle($request);
    }
}
