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

/**
 * Class Application
 *
 * PSR-15 стандартын RequestHandlerInterface-г хэрэгжүүлсэн Application класс.
 * Энэ нь HTTP хүсэлтүүдийг дараалсан middleware-ээр дамжуулж,
 * маршрутын callback эсвэл Controller->action-г ажиллуулж
 * PSR-7 Response буцаах үндсэн цөм модуль юм.
 *
 * Үндсэн үүргүүд:
 *  - Router үүсгэх, маршрут бүртгэх
 *  - Middleware стек удирдах (PSR-15 Middleware болон Closure)
 *  - Хүсэлтийг маршрутад тохируулах (Router::match)
 *  - Controller/action эсвэл Closure route ажиллуулах
 *  - Response багцын NonBodyResponse-г хэрэглэж fallback хариу буцаах
 *
 * Энэ багц нь codesaur/router ба codesaur/http-message багцуудад тулгуурлана.
 *
 * @package codesaur\Http\Application
 */
class Application implements RequestHandlerInterface
{
    /**
     * @var RouterInterface
     *
     * Application дотор ашиглагдах үндсэн Router.
     * Энэ нь бүх route pattern, method mapping, параметр тайлбарлалтыг гүйцэтгэнэ.
     */
    protected RouterInterface $router;

    /**
     * @var array
     *
     * Middleware жагсаалт.
     *
     * Дараах төрлүүдийг хүлээн авна:
     *  - PSR-15 MiddlewareInterface
     *  - Closure middleware ($request, $handler)
     *  - RouterInterface (merge хийнэ)
     *  - ExceptionHandlerInterface (глобал exception handler болгоно)
     */
    private array $_middlewares = [];

    /**
     * Application үүсэх үед шинэ Router автоматаар үүснэ.
     */
    public function __construct()
    {
        $this->router = new Router();
    }

    /**
     * Router-ийн аливаа public method-ийг Application-оор шууд дуудах боломж олгоно.
     *
     * Жишээ:
     *   $app->GET('/home', fn() => ...);
     *   $app->POST(...);
     *
     * @param string $name Дуудах функцийн нэр
     * @param array $arguments Аргументууд
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return \call_user_func_array([$this->router, $name], $arguments);
    }

    /**
     * Middleware, Router эсвэл ExceptionHandler бүртгэх.
     *
     * @param mixed $object
     * @return mixed|void
     *
     * @throws \InvalidArgumentException Буруу төрлийн объект дамжуулсан үед
     */
    public function use($object)
    {
        // PSR-15 Middleware болон Closure
        if ($object instanceof MiddlewareInterface || $object instanceof \Closure) {
            $this->_middlewares[] = $object;

        // Өөр router-ийн маршрутыг үндсэн router-т нэгтгэж бүртгэх
        } elseif ($object instanceof RouterInterface) {
            $this->router->merge($object);

        // Exception handler бүртгэх
        } elseif ($object instanceof ExceptionHandlerInterface) {
            return \set_exception_handler([$object, 'exception']);
        } else {
            throw new \InvalidArgumentException("Unsupported object passed to Application::use()");
        }
    }

    /**
     * {@inheritdoc}
     *
     * PSR-15 RequestHandlerInterface::handle()–ийн хэрэгжилт.
     *
     * Энэ функц нь:
     *
     *  1. Middleware queue-г бэлтгэнэ
     *  2. Эцсийн route matcher callback-г queue-н төгсгөлд нэмнэ
     *  3. Middleware-үүдийг дарааллаар нь ажиллуулна
     *  4. Тохирох маршрут олдох юм бол Controller/action эсвэл Closure-г дуудаж Response үүсгэнэ
     *
     * @param ServerRequestInterface $request PSR-7 ServerRequest
     * @return ResponseInterface PSR-7 Response
     *
     * @throws \Error Маршрут олдоогүй буюу controller/action байхгүй бол
     * @throws \BadMethodCallException Controller дотор action байхгүй үед
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Middleware жагсаалт + route matcher callback-г нэгтгэх
        $callbacks = $this->_middlewares;

        // Эцсийн middleware: маршрут match + controller/action дуудах
        $callbacks[] = function ($request) {

            // Request URI path
            $path = \rawurldecode($request->getUri()->getPath());

            // Document root-с гадуур байрлах үед замыг зөв тооцоолох
            if (($lngth = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
                $path = '/' . \ltrim(\substr($path, $lngth), '/');
            }

            if ($path === '') {
                $path = '/';
            }

            // Route match хийх
            $rule = $this->router->match($path, $request->getMethod());
            if (!$rule instanceof Callback) {
                throw new \Error("Unknown route pattern [$path]", 404);
            }

            // Route parameters → Request attributes
            $params = [];
            foreach ($rule->getParameters() as $param => $value) {
                $params[$param] = $value;
            }

            $request = $request
                ->withAttribute('params', $params)
                ->withAttribute('router', $this->router);

            $callable = $rule->getCallable();

            /**
             * 1) Closure route
             */
            if ($callable instanceof \Closure) {
                $response = \call_user_func_array($callable, [$request]);

            /**
             * 2) Controller/action route
             */
            } else {
                $controllerClass = $callable[0];
                if (!\class_exists($controllerClass)) {
                    throw new \Error("$controllerClass is not available", 501);
                }

                $action = $callable[1];
                $controller = new $controllerClass($request);
                if (!\method_exists($controller, $action)) {
                    throw new \BadMethodCallException(
                        __CLASS__ . ": Action named $action is not part of $controllerClass", 501
                    );
                }

                $response = \call_user_func_array([$controller, $action], $params);
            }

            // ResponseInterface биш тохиолдолд fallback
            return $response instanceof ResponseInterface
                ? $response
                : new NonBodyResponse();
        };

        // Middleware runner-г үүсгэх
        \reset($callbacks);

        /**
         * Middleware queue-г PSR-15 стандартын дагуу дараалж гүйцэтгэгч дотоод Runner класс.
         */
        $runner = new class ($callbacks) implements RequestHandlerInterface {
            /** @var array Middleware queue */
            private $_queue;

            public function __construct($queue)
            {
                $this->_queue = $queue;
            }

            /**
             * Middleware-ийг дарааллаар нь ажиллуулах.
             *
             * @param ServerRequestInterface $request
             * @return ResponseInterface
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $current = \current($this->_queue);
                \next($this->_queue);

                // PSR-15 MiddlewareInterface
                if ($current instanceof MiddlewareInterface) {
                    return $current->process($request, $this);

                // Closure middleware
                } elseif ($current instanceof \Closure) {
                    return \call_user_func_array($current, [$request, $this]);
                }

                // Эцсийн route matcher
                return $current($request);
            }
        };

        // Request-г middleware chain-аар дамжуулах
        return $runner->handle($request);
    }
}
