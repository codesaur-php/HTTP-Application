<?php

namespace codesaur\Http\Application;

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
    protected RouterInterface $router;
    
    private array $_middlewares = [];
    
    public function __construct()
    {
        $this->router = new Router();
    }

    public function __call(string $name, array $arguments)
    {
        return \call_user_func_array([$this->router, $name], $arguments);
    }
    
    public function use($object)
    {
        if ($object instanceof MiddlewareInterface
            || $object instanceof \Closure
        ) {
            $this->_middlewares[] = $object;
        } elseif($object instanceof RouterInterface) {
            $this->router->merge($object);
        } elseif ($object instanceof ExceptionHandlerInterface) {
            return \set_exception_handler([$object, 'exception']);
        } else {
            throw new \InvalidArgumentException();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $callbacks = $this->_middlewares;
        $callbacks[] = function($request) {
            $requestPath = $request->getRequestTarget();
            if (($pos = \strpos($requestPath, '?')) !== false) {
                $requestPath = \substr($requestPath, 0, $pos);
            }
            $scriptPath = \dirname($request->getServerParams()['SCRIPT_NAME']);
            if (($lngth = \strlen($scriptPath)) > 1) {
                $requestPath = \substr($requestPath, $lngth);
                $requestPath = '/' . \ltrim($requestPath, '/');
            }
            
            $rule = $this->router->match($requestPath, $request->getMethod());
            if (!$rule instanceof Callback) {
                throw new \Error("Unknown route pattern [$requestPath]", 404);
            }            
            $params = [];
            foreach ($rule->getParameters() as $param => $value) {
               $params[$param] = $value;
            }
            $request = $request->withAttribute('params', $params);
            $request = $request->withAttribute('router', $this->router);
            
            $callable = $rule->getCallable();
            if ($callable instanceof \Closure) {
                $response = \call_user_func_array($callable, [$request]);
            } else {
                $controllerClass = $callable[0];
                if (!\class_exists($controllerClass)) {
                    throw new \Error("$controllerClass is not available", 501);
                }

                $action = $callable[1];
                $controller = new $controllerClass($request);
                if (!\method_exists($controller, $action)) {
                    throw new \BadMethodCallException(__CLASS__ . ": Action named $action is not part of $controllerClass", 501);
                }
                $response = \call_user_func_array([$controller, $action], $params);
            }

            return $response instanceof ResponseInterface ? $response : new NonBodyResponse();
        };
        
        \reset($callbacks);
        $runner = new class ($callbacks) implements RequestHandlerInterface
        {
            private $_queue;
            
            public function __construct($queue)
            {
                $this->_queue = $queue;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $current = \current($this->_queue);
                \next($this->_queue);
                
                if ($current instanceof MiddlewareInterface) {
                    return $current->process($request, $this);
                } elseif ($current instanceof \Closure) {
                    return \call_user_func_array($current, [$request, $this]);
                }
                
                return $current($request);
            }
        };
        
        return $runner->handle($request);
    }
}
