<?php declare(strict_types=1);

namespace codesaur\Http\Application;

use Closure;
use Error;
use BadMethodCallException;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Router\Router;
use codesaur\Http\Message\Response;

class Application implements RequestHandlerInterface
{
    protected $router;
    
    function __construct()
    {
        $this->router = new Router();
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function __call(string $name, array $arguments)
    {
        if (count($arguments) !== 0) {
            if ($name === 'use') {
                if ($arguments[0] instanceof Router) {
                    return $this->router->merge($arguments[0]);
                } elseif ($arguments[0] instanceof ExceptionHandlerInterface) {
                    return set_exception_handler(array($arguments[0], 'exception'));
                }
            } else {
                return call_user_func_array(array($this->router, $name), $arguments);
            }
        }
        
        throw new BadMethodCallException("Bad method [$name] call for " . __CLASS__ . '!');
    }
    
    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $script_path = dirname($request->getServerParams()['SCRIPT_NAME']);                
        $uri_path = rawurldecode($request->getUri()->getPath());
        $target_path = str_replace($script_path, '', $uri_path);
        $route = $this->router->match($target_path, $request->getMethod());
        if (!isset($route)) {
            throw new Error("Unknown route pattern [$target_path]!", 404);
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
                throw new Error("$controllerClass is not available!", 501);
            }

            $action = $callback[1] ?? 'index';
            $controller = new $controllerClass($request);
            if (!method_exists($controller, $action)) {
                throw new BadMethodCallException("Action named $action is not part of $controllerClass!");
            }

            $response = call_user_func_array(array($controller, $action), $route->getParameters());       
        }
        
        return $response instanceof ResponseInterface ? $response : new Response();
    }
}
