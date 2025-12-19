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
 * Application Class
 *
 * PSR-15 стандартын RequestHandlerInterface-г хэрэгжүүлсэн HTTP Application цөм класс.
 *
 * Энэ класс нь HTTP хүсэлтүүдийг дараалсан middleware-ээр дамжуулж,
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
 * @author Narankhuu
 * @since 1.0.0
 * @implements RequestHandlerInterface
 */
class Application implements RequestHandlerInterface
{
    /**
     * Application дотор ашиглагдах үндсэн Router instance.
     *
     * Энэ нь бүх route pattern, method mapping, параметр тайлбарлалтыг гүйцэтгэнэ.
     * Router-ийн бүх public method-үүдийг Application-оор шууд дуудах боломжтой.
     *
     * @var RouterInterface
     */
    protected RouterInterface $router;

    /**
     * Middleware жагсаалт (queue).
     *
     * Дараах төрлүүдийг хүлээн авна:
     *  - PSR-15 MiddlewareInterface
     *  - Closure middleware ($request, $handler)
     *  - RouterInterface (merge хийнэ)
     *  - ExceptionHandlerInterface (глобал exception handler болгоно)
     *
     * @var array<int, MiddlewareInterface|\Closure>
     */
    private array $_middlewares = [];

    /**
     * Application конструктор.
     *
     * Application үүсэх үед шинэ Router instance автоматаар үүснэ.
     * Энэ Router-г ашиглан маршрутуудыг бүртгэж болно.
     */
    public function __construct()
    {
        $this->router = new Router();
    }

    /**
     * Router-ийн аливаа public method-ийг Application-оор шууд дуудах боломж олгоно.
     *
     * Энэ нь magic method бөгөөд Router классын бүх public method-үүдийг
     * Application instance-оор шууд дуудах боломжийг олгодог.
     *
     * @param string $name Дуудах функцийн нэр (жишээ: GET, POST, PUT, DELETE гэх мэт)
     * @param array<int, mixed> $arguments Аргументууд
     * @return mixed Router method-ийн буцаах утга
     *
     * @example
     * $app->GET('/home', fn($req) => echo 'Home');
     * $app->POST('/api/users', [UserController::class, 'create']);
     * $app->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');
     */
    public function __call(string $name, array $arguments)
    {
        return \call_user_func_array([$this->router, $name], $arguments);
    }

    /**
     * Middleware, Router эсвэл ExceptionHandler бүртгэх.
     *
     * Энэ метод нь дараах төрлийн объектуудыг хүлээн авна:
     * - MiddlewareInterface: PSR-15 стандартын middleware
     * - Closure: Closure middleware function
     * - RouterInterface: Өөр router-ийн маршрутуудыг нэгтгэх
     * - ExceptionHandlerInterface: Глобал exception handler бүртгэх
     *
     * @param MiddlewareInterface|\Closure|RouterInterface|ExceptionHandlerInterface $object
     *        Бүртгэх объект
     * @return mixed|void ExceptionHandler бүртгэх үед өмнөх handler буцаана
     *
     * @throws \InvalidArgumentException Буруу төрлийн объект дамжуулсан үед
     *
     * @example
     * $app->use(new MyMiddleware());
     * $app->use(function ($req, $handler) { return $handler->handle($req); });
     * $app->use(new ExceptionHandler());
     * $app->use(new CustomRouter());
     */
    public function use($object)
    {
        // PSR-15 Middleware болон Closure
        if ($object instanceof MiddlewareInterface || $object instanceof \Closure) {
            $this->_middlewares[] = $object;
            return;

        // Өөр router-ийн маршрутыг үндсэн router-т нэгтгэж бүртгэх
        } elseif ($object instanceof RouterInterface) {
            $this->router->merge($object);
            return;

        // Exception handler бүртгэх
        } elseif ($object instanceof ExceptionHandlerInterface) {
            return \set_exception_handler([$object, 'exception']);
        }

        throw new \InvalidArgumentException(
            "Unsupported object passed to Application::use(). " .
            "Expected MiddlewareInterface, Closure, RouterInterface, or ExceptionHandlerInterface."
        );
    }

    /**
     * {@inheritdoc}
     *
     * PSR-15 RequestHandlerInterface::handle()-ийн хэрэгжилт.
     *
     * Энэ функц нь HTTP хүсэлтийг боловсруулах бүрэн процесс-ийг гүйцэтгэнэ:
     *
     *  1. Middleware queue-г бэлтгэнэ
     *  2. Эцсийн route matcher callback-г queue-н төгсгөлд нэмнэ
     *  3. Middleware-үүдийг дарааллаар нь ажиллуулна (onion model)
     *  4. Тохирох маршрут олдох юм бол Controller/action эсвэл Closure-г дуудаж Response үүсгэнэ
     *  5. Response-г буцаана (ResponseInterface биш бол NonBodyResponse fallback)
     *
     * @param ServerRequestInterface $request PSR-7 ServerRequest объект
     * @return ResponseInterface PSR-7 Response объект
     *
     * @throws \Error Маршрут олдоогүй (404) буюу controller class байхгүй (501) үед
     * @throws \BadMethodCallException Controller дотор action method байхгүй үед (501)
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
            // Жишээ: /subdirectory/index.php → /subdirectory → path-г зөв тохируулах
            if (($lngth = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
                $path = '/' . \ltrim(\substr($path, $lngth), '/');
            }

            // Хоосон path-г root path болгох
            if ($path === '') {
                $path = '/';
            }

            // Route match хийх - URI path болон HTTP method-оор маршрут олох
            $rule = $this->router->match($path, $request->getMethod());
            if (!$rule instanceof Callback) {
                throw new \Error("Unknown route pattern [$path]", 404);
            }

            // Route parameters → Request attributes
            // Жишээ: /user/{int:id} → ['id' => 123] → $request->getAttribute('params')['id']
            $params = [];
            foreach ($rule->getParameters() as $param => $value) {
                $params[$param] = $value;
            }

            // Request attributes-д route parameters болон router instance нэмэх
            // Controller-үүд эдгээр attributes-г ашиглаж болно
            $request = $request
                ->withAttribute('params', $params)
                ->withAttribute('router', $this->router);

            $callable = $rule->getCallable();

            /**
             * 1) Closure route
             * Жишээ: $app->GET('/hello', function($req) { ... });
             */
            if ($callable instanceof \Closure) {
                $response = \call_user_func_array($callable, [$request]);

            /**
             * 2) Controller/action route
             * Жишээ: $app->GET('/user/{id}', [UserController::class, 'show']);
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

                // Route parameters-г action method-ийн аргумент болгон дамжуулна
                // Жишээ: /user/{int:id} → UserController::show(int $id)
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
         *
         * Энэ нь anonymous class бөгөөд middleware chain-ийг onion model-ээр ажиллуулна.
         * Runner нь middleware queue-г дарааллаар нь ажиллуулж, эцэст нь route matcher callback-г
         * дуудаж Controller/action эсвэл Closure route-г гүйцэтгэнэ.
         *
         * @var RequestHandlerInterface
         */
        $runner = new class ($callbacks) implements RequestHandlerInterface {
            /**
             * Middleware queue (array of MiddlewareInterface or Closure).
             *
             * Queue нь дараах төрлүүдийг агуулна:
             * - PSR-15 MiddlewareInterface: process($request, $handler) method-тэй
             * - Closure: function($request, $handler) хэлбэрийн middleware
             * - callable: Эцсийн route matcher callback function($request)
             *
             * @var array<int, MiddlewareInterface|\Closure|callable>
             */
            private array $_queue;

            /**
             * Runner конструктор.
             *
             * Middleware queue-г хадгалж, дараагийн handle() дуудлагад ашиглана.
             *
             * @param array<int, MiddlewareInterface|\Closure|callable> $queue Middleware queue
             */
            public function __construct(array $queue)
            {
                $this->_queue = $queue;
            }

            /**
             * Middleware-ийг дарааллаар нь ажиллуулах (PSR-15 onion model).
             *
             * Энэ метод нь queue-н дараагийн middleware-г авч ажиллуулна:
             * - PSR-15 MiddlewareInterface бол process() method-г дуудна
             * - Closure бол ($request, $this) аргументуудаар дуудна
             * - callable бол ($request) аргументоор дуудна (route matcher)
             *
             * @param ServerRequestInterface $request PSR-7 ServerRequest объект
             * @return ResponseInterface PSR-7 Response объект
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $current = \current($this->_queue);
                \next($this->_queue);

                // PSR-15 MiddlewareInterface
                if ($current instanceof MiddlewareInterface) {
                    return $current->process($request, $this);
                }

                // Closure middleware
                if ($current instanceof \Closure) {
                    return \call_user_func_array($current, [$request, $this]);
                }

                // Эцсийн route matcher callback
                return $current($request);
            }
        };

        // Request-г middleware chain-аар дамжуулах
        return $runner->handle($request);
    }
}
