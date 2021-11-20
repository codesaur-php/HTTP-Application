<?php

namespace codesaur\Http\Application;

use Closure;
use Exception;
use BadMethodCallException;
use InvalidArgumentException;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Router\Router;
use codesaur\Router\Callback;
use codesaur\Router\RouterInterface;
use codesaur\Http\Message\NonBodyResponse;

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
            $script_path = $request->getServerParams()['SCRIPT_TARGET_PATH'] ?? null;
            if (!isset($script_path)) {
                $script_path = dirname($request->getServerParams()['SCRIPT_NAME']);
                if ($script_path == '\\' || $script_path == '/') {
                    $script_path = null;
                }
            }
            if (!empty($script_path)) {
                $uri_path = substr($uri_path, strlen($script_path));
            }
            if (empty($uri_path)) {
                $uri_path = '/';
            }
            $rule = $this->router->match($uri_path, $request->getMethod());
            if (!$rule instanceof Callback) {
                $pattern = rawurldecode($uri_path);
                throw new Exception("Unknown route pattern [$pattern]", 404);
            }
            
            $params = array();
            foreach ($rule->getParameters() as $param => $value) {
               $params[$param] = $value;
            }
            $request = $request->withAttribute('params', $params);
            $request = $request->withAttribute('router', $this->router);
            
            $callable = $rule->getCallable();
            if ($callable instanceof Closure) {
                $response = call_user_func_array($callable, array($request));
            } else {
                $controllerClass = $callable[0];
                if (!class_exists($controllerClass)) {
                    throw new Exception("$controllerClass is not available", 501);
                }

                $action = $callable[1];
                $controller = new $controllerClass($request);
                if (!method_exists($controller, $action)) {
                    throw new BadMethodCallException(__CLASS__ . ": Action named $action is not part of $controllerClass", 501);
                }
                $response = call_user_func_array(array($controller, $action), $rule->getParameters());
            }

            return $response instanceof ResponseInterface ? $response : new NonBodyResponse();
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
