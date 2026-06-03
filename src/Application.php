<?php

namespace codesaur\Http\Application;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Router\RouterInterface;

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
 *  - Олон Router-ийг агуулах ба нэгтгэн ажиллуулах (delegation)
 *  - Global middleware стек удирдах (PSR-15 Middleware болон Closure)
 *  - Хүсэлтийг бүх router-ээр дамжуулж first-match-wins зарчмаар тааруулах
 *  - Өмнө бүртгэсэн route-ийг зориудаар дарж бичих override lane (explicit override)
 *  - Per-route middleware ажиллуулах (Route::middleware([...]))
 *  - Controller/action эсвэл Closure route ажиллуулах
 *  - Handler ResponseInterface биш төрөл буцаавал constructor-оор өгсөн хариуны
 *    prototype-оос clone хийж fallback хариу буцаах
 *
 * **Олон Router дэмжих (multi-router delegation):**
 * codesaur/router v6-аас эхлэн Router::merge() устгагдсан тул Application нь олон
 * router-ийг өөрөө цуглуулж delegation хийдэг боллоо. Router-уудыг use(RouterInterface)
 * дуудах замаар нэмнэ, use()-ийн дарааллаар (first-added-wins) шалгагдана.
 *
 *   $app = new Application(new NonBodyResponse());  // fallback хариуны prototype
 *   $app->use(new ApiRouter());                     // эхэлж шалгагдана
 *   $app->use(new AdminRouter());                   // дараа нь
 *
 * Default match-ийн эрэмбэ нь registration order (first-added-wins) - энэ нь
 * предиктабл бөгөөд best practice-ийг дагасан.
 *
 * **Explicit override (override lane):**
 * Өмнө бүртгэсэн route-ийг (жишээ: /profile) зориудаар дарж бичих
 * шаардлагатай үед override(RouterInterface) ашиглана. Override lane-д буй
 * router-ууд ердийн router-ээс өмнө шалгагдах тул тэдгээрийн route ялна. Explicit
 * override нь best practice-ийг дагасан - далд биш, bootstrap дотор ил харагдана.
 * Голдуу vendor дотор зарлагдсан, шууд засах боломжгүй route-ийг дарж бичихэд хэрэгтэй.
 *
 *   $app->use(new ProfileRouter());            // өмнө бүртгэсэн /profile
 *   $app->override(new ThemeProfileRouter());  // ил: эдгээр зориудаар ялна
 *
 * Энэ багц нь codesaur/router-д тулгуурлах ба HTTP message-ийн хувьд зөвхөн
 * PSR-7 interface-д тулгуурлана: хүсэлтийг `handle(ServerRequestInterface)`-ээр
 * хүлээн авч, хариуны fallback prototype-ийг constructor-оор авна.
 *
 * @package codesaur\Http\Application
 * @author Narankhuu
 * @since 1.0.0
 * @implements RequestHandlerInterface
 */
class Application implements RequestHandlerInterface
{
    /**
     * Application-д бүртгэлтэй ердийн Router instance-уудын жагсаалт.
     *
     * use(RouterInterface)-ээр нэмэгдсэн дарааллаар хадгалагдана (registration order).
     * Зөвхөн use() дотроос л set хийгддэг тул private.
     *
     * Эрэмбэ - match/generate/pattern гэх мэт lookup-д first-added-wins:
     * router-уудыг бүртгэсэн дарааллаар (эхэлж нэмэгдсэнээс) шалгана. Энэ нь
     * предиктабл бөгөөд best practice-ийг дагасан.
     *   - match(): эхний router-аас эхлэн match хайх, эхэлж таарвал тэр буцаана
     *   - generate(), pattern(): эхний router-аас эхлэн name хайх, эхэлж олдсон pattern-ийг буцаана
     *   - getRoutes(): бүх router-ийн route-уудыг нэгтгэх (first-added wins on (pattern, method) collision)
     *
     * Өмнө бүртгэсэн route-ийг зориудаар дарж бичихийг хүсвэл $overrides lane ашиглана.
     *
     * @var list<RouterInterface>
     */
    private array $routers = [];

    /**
     * Override lane - өмнө бүртгэсэн route-ийг зориудаар дарж бичих Router-уудын жагсаалт.
     *
     * override(RouterInterface)-ээр нэмэгдэнэ. match/generate/pattern/getRoutes
     * бүгд ердийн $routers-ээс өмнө энэ lane-ийг шалгана - тиймээс энд буй
     * route-ууд ердийн router-ийн ижил path-ийг ялна.
     *
     * Энэ нь override-ийг далд (registration order-ийн санамсаргүй гаж нөлөө) бус,
     * ил зориудын үйлдэл болгоно. Lane дотроо first-added-wins.
     *
     * Зөвхөн override() дотроос л set хийгддэг тул private.
     *
     * @var list<RouterInterface>
     */
    private array $overrides = [];

    /**
     * Global middleware жагсаалт (queue).
     *
     * Дараах төрлүүдийг хүлээн авна:
     *  - PSR-15 MiddlewareInterface
     *  - Closure middleware ($request, $handler)
     *
     * Зөвхөн use() дотроос л set хийгддэг тул private.
     *
     * @var array<int, MiddlewareInterface|\Closure>
     */
    private array $middlewares = [];

    /**
     * Application-ийн mount path (URL prefix).
     *
     * Хоосон бол ('') Application нь root-д сууж байна (mount хийгээгүй).
     * Утгатай үед (жишээ: '/dashboard') Application нь тухайн path-д mount
     * хийгдсэн бөгөөд:
     *
     *  - match() нь request path-аас mount prefix-ийг автоматаар зүсээд
     *    Router-уудад дамжуулна
     *  - generate(), pattern() нь буцаах URL дээр mount prefix-ийг урдаас
     *    автоматаар нэмнэ
     *  - getRoutes() нь route pattern бүрд mount prefix хавсаргасан
     *    бүтэн URL pattern-уудыг буцаана
     *  - Mount prefix-ээс гадуур path-ийг match() нь null буцаах
     *    (route таарахгүй гэж үзэх)
     *
     * Энэ нь Application-ийг sub-application болгож олон mount point-д
     * (жишээ: entry point дээр Dashboard-ийг /dashboard-д, Api-г /api-д) ашиглах
     * боломж олгоно. Router-ууд mount path-ийг мэдэхгүй - reusable.
     *
     * Зөвхөн mount() дотроос л set хийгддэг, getMountPath()-ээр уншина тул private.
     *
     * @var string
     */
    private string $mountPath = '';

    /**
     * Fallback хариуны prototype.
     *
     * Controller/action эсвэл Closure нь ResponseInterface биш төрөл буцаавал
     * handle() энэ prototype-оос clone хийж хүчинтэй хариу буцаана. Концрет
     * Response implementation-ийг constructor-оор гаднаас дамжуулдаг тул энэ багц
     * аль нэг тодорхой PSR-7 implementation-д тулгуурлахгүй.
     *
     * Зөвхөн constructor дотроос л set хийгддэг тул private.
     *
     * @var ResponseInterface
     */
    private ResponseInterface $responsePrototype;

    /**
     * Application-г эхлүүлэх.
     *
     * @param ResponseInterface $responsePrototype Handler ResponseInterface
     *        биш төрөл буцаасан үед fallback болгон clone хийгдэх хариуны prototype
     */
    public function __construct(ResponseInterface $responsePrototype)
    {
        $this->responsePrototype = $responsePrototype;
    }

    /**
     * Middleware, Router эсвэл ExceptionHandler бүртгэх.
     *
     * Энэ метод нь дараах төрлийн объектуудыг хүлээн авна:
     * - MiddlewareInterface: PSR-15 стандартын middleware
     * - Closure: Closure middleware function
     * - RouterInterface: Router (multi-router delegation)
     * - ExceptionHandlerInterface: Глобал exception handler бүртгэх
     *
     * **Multi-router delegation:**
     * `use(RouterInterface)` нь Router-ийг Application-ы ердийн router-ийн жагсаалтад
     * append хийнэ. Match-ийн эрэмбэ нь registration order (first-added-wins) -
     * эхэлж нэмэгдсэн router-ийн route ялна. Өмнө бүртгэсэн route-ийг зориудаар
     * дарж бичихийг хүсвэл override() ашиглана.
     *
     *   $app->use(new ApiRouter());    // эхэлж шалгагдана
     *   $app->use(new AdminRouter());  // дараа нь шалгагдана
     *
     * @param MiddlewareInterface|\Closure|RouterInterface|ExceptionHandlerInterface $object Бүртгэх объект
     * @return mixed|void ExceptionHandler бүртгэх үед өмнөх handler буцаана
     *
     * @throws \InvalidArgumentException Буруу төрлийн объект дамжуулсан үед
     *
     * @example
     * $app->use(new MyMiddleware());
     * $app->use(function ($req, $handler) { return $handler->handle($req); });
     * $app->use(new ApiRouter());           // router
     * $app->use(new ExceptionHandler());
     */
    public function use($object)
    {
        // PSR-15 Middleware болон Closure
        if ($object instanceof MiddlewareInterface || $object instanceof \Closure) {
            $this->middlewares[] = $object;
            return;

        // Multi-router delegation - router цуглуулна
        } elseif ($object instanceof RouterInterface) {
            $this->routers[] = $object;
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
     * Override Router бүртгэх - өмнө бүртгэсэн route-ийг зориудаар дарж бичих.
     *
     * Override lane-д нэмэгдсэн router-ууд match/generate/pattern/getRoutes бүгдэд
     * ердийн use()-ийн router-ээс өмнө шалгагдана. Тиймээс энд буй route нь ердийн
     * router-ийн ижил path-ийг ялж дарж бичнэ.
     *
     * Энэ нь override-ийг ил, зориудын үйлдэл болгоно (explicit override best practice).
     * Зүгээр use()-ийн дараалалд найдаж далд override хийхгүй - bootstrap уншихад
     * override нь тодорхой харагдана.
     *
     * Override-ийг зөвхөн developer шууд засах боломжгүй route (жишээ: vendor багц
     * дотор зарлагдсан)-д л хэрэглэхийг зөвлөнө. Хэрэв route өөрийн project дотор
     * байгаа бол шууд эх кодон дээр нь засах нь зөв - override хийх нь илүүц.
     *
     * Жишээ - өмнө бүртгэсэн profile хуудсыг developer-ийн theme controller-руу шилжүүлэх:
     *   $app->use(new ProfileRouter());            // өмнө бүртгэсэн /profile
     *
     *   $themeProfile = new Router();
     *   $themeProfile->GET('/profile', [ThemeProfileController::class, 'show'])->name('profile');
     *   $app->override($themeProfile);             // одоо энэ ялна
     *
     * @param RouterInterface $router Override хийх route-уудыг агуулсан Router
     * @return static Fluent chain-д ашиглахын тулд $this буцаана
     */
    public function override(RouterInterface $router): static
    {
        $this->overrides[] = $router;
        return $this;
    }

    /**
     * Application-ийг URL prefix-д mount хийнэ.
     *
     * Mount хийгдсэний дараа Router-уудын бүх route нь автоматаар
     * энэ prefix-д хадгалагдсан гэж тооцогдоно. Router-ууд өөрсдөө
     * mount path-ийг мэдэхгүй - reusable байна.
     *
     * Жишээ:
     *   $app = (new Dashboard\Application())->mount('/dashboard');
     *   // Router-д GET('/users') гэж бүртгэсэн route нь /dashboard/users -д сонгогдоно.
     *   // generate('users') нь '/dashboard/users' буцаана.
     *
     * Prefix нь сонголтоор leading/trailing slash-тай байж болно:
     *   mount('/dashboard')  -> '/dashboard'
     *   mount('dashboard')   -> '/dashboard'
     *   mount('/dashboard/') -> '/dashboard'
     *   mount('') эсвэл mount('/') -> mount-гүй (хоосон)
     *
     * @param string $prefix URL prefix (mount point)
     * @return static Fluent chain-д ашиглахын тулд $this буцаана
     */
    public function mount(string $prefix): static
    {
        $prefix = \trim($prefix, '/');
        $this->mountPath = $prefix === '' ? '' : "/$prefix";
        return $this;
    }

    /**
     * Одоогийн mount path-ийг буцаана (introspection).
     *
     * @return string Mount хийгээгүй бол '' (хоосон), эсвэл '/dashboard' гэх мэт
     */
    public function getMountPath(): string
    {
        return $this->mountPath;
    }

    /**
     * Route match хайна - override lane эхэлж, дараа нь first-added-wins.
     *
     * Mount path тогтоосон бол (mount() ашигласан) request path-аас
     * mount prefix-ийг автоматаар зүсэж Router-уудад дамжуулна.
     * Boundary шалгах: /dashboard прэфикс нь /dashboard, /dashboard/users-д
     * таарна гэхдээ /dashboardx-д таарахгүй.
     *
     * Mount prefix-ээс гадуур path-д null буцаана - Application-ийн route
     * биш гэж үзэх. handle() дотор энэ нь 404 болж хувирна.
     *
     * Хайх эрэмбэ:
     *   1) override lane (override()-ээр нэмсэн) - эдгээр зориудаар ялна
     *   2) ердийн router-ууд (use()-ээр нэмсэн) - first-added-wins
     * Хоёр lane дотроо тус бүр бүртгэсэн дарааллаар, эхэлж таарсан үр дүнг буцаана.
     * Аль ч router-д таарахгүй бол null.
     *
     * @param string $path   Хайх URL path
     * @param string $method HTTP method
     * @return array{0: callable|array{class-string, string}, 1: array<string, mixed>, 2: list<class-string|callable|MiddlewareInterface>}|null
     */
    public function match(string $path, string $method): ?array
    {
        if ($this->mountPath !== '') {
            if ($path === $this->mountPath) {
                $path = '/';
            } elseif (\str_starts_with($path, $this->mountPath . '/')) {
                $path = \substr($path, \strlen($this->mountPath));
            } else {
                return null;
            }
        }

        // 1) Override lane эхэлж шалгана - энд буй route ердийн router-ийг ялна
        foreach ($this->overrides as $router) {
            $result = $router->match($path, $method);
            if ($result !== null) {
                return $result;
            }
        }
        // 2) Ердийн router-ууд - first-added-wins (бүртгэсэн дарааллаар)
        foreach ($this->routers as $router) {
            $result = $router->match($path, $method);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    /**
     * Route name-ээр URL үүсгэнэ - override lane эхэлж, дараа нь first-found-wins.
     *
     * Эхлээд override lane, дараа нь ердийн router-уудыг бүртгэсэн дарааллаар
     * loop хийж generate-ийг try хийнэ. OutOfRangeException (name олдоогүй) бол
     * дараагийн router-руу шилжинэ. Бусад exception (тухайлбал InvalidArgumentException
     * param-ийн төрөл буруу үед) шууд дээш хаягдана.
     *
     * @param string $ruleName Маршрутын нэр
     * @param array<string, mixed> $params Параметрүүд
     * @return string Үүсгэсэн URL
     *
     * @throws \OutOfRangeException Аль ч router-д name олдохгүй бол
     * @throws \InvalidArgumentException Параметрийн төрөл буруу бол
     */
    public function generate(string $ruleName, array $params = []): string
    {
        // 1) Override lane эхэлж, 2) дараа нь ердийн router-ууд (first-found-wins)
        foreach ($this->overrides as $router) {
            try {
                return $this->mountPath . $router->generate($ruleName, $params);
            } catch (\OutOfRangeException $e) {
                // Энэ router-д байхгүй - дараагийг шалгана
            }
        }
        foreach ($this->routers as $router) {
            try {
                return $this->mountPath . $router->generate($ruleName, $params);
            } catch (\OutOfRangeException $e) {
                // Энэ router-д байхгүй - дараагийг шалгана
            }
        }
        throw new \OutOfRangeException(
            __CLASS__ . ": Route with rule named [$ruleName] not found in any registered router"
        );
    }

    /**
     * Route name-ээр pattern хайна - override lane эхэлж, дараа нь first-found-wins.
     *
     * @param string $ruleName Маршрутын нэр
     * @return string Filter prefix хасагдсан pattern
     *
     * @throws \OutOfRangeException Аль ч router-д name олдохгүй бол
     */
    public function pattern(string $ruleName): string
    {
        // 1) Override lane эхэлж, 2) дараа нь ердийн router-ууд (first-found-wins)
        foreach ($this->overrides as $router) {
            try {
                return $this->mountPath . $router->pattern($ruleName);
            } catch (\OutOfRangeException $e) {
                // Энэ router-д байхгүй - дараагийг шалгана
            }
        }
        foreach ($this->routers as $router) {
            try {
                return $this->mountPath . $router->pattern($ruleName);
            } catch (\OutOfRangeException $e) {
                // Энэ router-д байхгүй - дараагийг шалгана
            }
        }
        throw new \OutOfRangeException(
            __CLASS__ . ": Route with rule named [$ruleName] not found in any registered router"
        );
    }

    /**
     * Бүх router-ийн route-уудыг нэгтгэж буцаана.
     *
     * Эрэмбэ нь match()-тэй ижил: override lane эхэлж, дараа нь ердийн router-ууд.
     * (pattern, method) collision дээр эхэлж бүртгэгдсэн нь хадгалагдана
     * (override lane > ердийн router, lane дотроо first-added-wins). Энэ нь
     * match()-ийн эрэмбэтэй тогтворжсон - introspection-д харагдах route нь яг
     * runtime-д ажиллах route юм.
     *
     * @return array<string, array<string, array{
     *     0: callable|array{class-string, string},
     *     1: list<class-string|callable|MiddlewareInterface>
     * }>>
     */
    public function getRoutes(): array
    {
        $routes = [];
        // Override lane эхэлж, дараа нь ердийн router-ууд. Эхэлж олдсон (pattern, method)
        // хадгалагдана (!isset guard) - тиймээс override > ердийн, lane дотроо first-added-wins.
        foreach ($this->overrides as $router) {
            foreach ($router->getRoutes() as $pattern => $methodMap) {
                $fullPattern = $this->mountPath . $pattern;
                foreach ($methodMap as $method => $entry) {
                    if (!isset($routes[$fullPattern][$method])) {
                        $routes[$fullPattern][$method] = $entry;
                    }
                }
            }
        }
        foreach ($this->routers as $router) {
            foreach ($router->getRoutes() as $pattern => $methodMap) {
                $fullPattern = $this->mountPath . $pattern;
                foreach ($methodMap as $method => $entry) {
                    if (!isset($routes[$fullPattern][$method])) {
                        $routes[$fullPattern][$method] = $entry;
                    }
                }
            }
        }
        return $routes;
    }

    /**
     * Application-д use()-ээр бүртгэлтэй ердийн router-уудыг буцаана (introspection).
     *
     * Router-ууд use()-ийн дарааллаар жагсагдсан. Override lane-ийг getOverrides() буцаана.
     *
     * @return list<RouterInterface>
     */
    public function getRouters(): array
    {
        return $this->routers;
    }

    /**
     * Override lane-д override()-ээр бүртгэлтэй router-уудыг буцаана (introspection).
     *
     * Эдгээр router-ууд match/generate/pattern/getRoutes бүгдэд ердийн router-ээс
     * өмнө шалгагдаж бүртгэсэн route-ийг дарж бичдэг. override()-ийн дарааллаар жагсагдсан.
     *
     * @return list<RouterInterface>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * {@inheritdoc}
     *
     * PSR-15 RequestHandlerInterface::handle()-ийн хэрэгжилт.
     *
     * Энэ функц нь HTTP хүсэлтийг боловсруулах бүрэн процесс-ийг гүйцэтгэнэ:
     *
     *  1. Global middleware queue-г бэлтгэнэ
     *  2. Эцсийн route matcher callback-г queue-н төгсгөлд нэмнэ
     *  3. Middleware-үүдийг дарааллаар нь ажиллуулна (onion model)
     *  4. Application::match() дуудаж route хайна. match() дотор mount prefix
     *     зүсэлт, олон router delegation хийгдэнэ.
     *  5. Per-route middleware-уудыг бэлдэж, эцэст нь Controller/action эсвэл
     *     Closure-г дуудаж Response үүсгэнэ
     *  6. Response-г буцаана (ResponseInterface биш бол constructor-оор өгсөн
     *     хариуны prototype-оос clone хийж fallback болгоно)
     *
     * **'application' request attribute:**
     * Application instance өөрөө `'application'` attribute-аар request-д очино.
     * Controller-ууд `$request->getAttribute('application')->generate($name)`
     * дуудах үед Application::generate() дуудагдан mount prefix
     * автоматаар прэпенд хийгдэнэ.
     *
     * @param ServerRequestInterface $request PSR-7 ServerRequest объект
     * @return ResponseInterface PSR-7 Response объект
     *
     * @throws \Error Маршрут олдоогүй (404) буюу controller class байхгүй (501) үед
     * @throws \BadMethodCallException Controller дотор action method байхгүй үед (501)
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Global middleware жагсаалт + route matcher callback-г нэгтгэх
        $callbacks = $this->middlewares;

        // Эцсийн middleware: маршрут match + per-route middleware + handler
        $callbacks[] = function ($request) {

            // Request URI path
            $path = \rawurldecode($request->getUri()->getPath());

            // Document root-с гадуур байрлах үед замыг зөв тооцоолох
            // Жишээ: /subdirectory/index.php -> /subdirectory -> path-г зөв тохируулах
            if (($lngth = \strlen(\dirname($request->getServerParams()['SCRIPT_NAME']))) > 1) {
                $path = '/' . \ltrim(\substr($path, $lngth), '/');
            }

            // Хоосон path-г root path болгох
            if ($path === '') {
                $path = '/';
            }

            // Application::match() дотор mount prefix зүсэлт, олон router-ийн
            // delegation хийгдэнэ. HEAD->GET fallback нь Router class-ийн
            // дотор хэрэгждэг тул энд давтан хэрэгжүүлэх шаардлагагүй.
            $result = $this->match($path, $request->getMethod());
            if ($result === null) {
                throw new \Error("Unknown route pattern [$path]", 404);
            }

            [$callable, $params, $routeMiddleware] = $result;

            // Request attributes-д route parameters болон Application instance-ийг
            // нэмэх. 'application' attribute нь Application өөрөө - Controller
            // generate('name') дуудахад Application::generate() дамжин mount
            // prefix автоматаар нэмэгдэнэ.
            $request = $request
                ->withAttribute('params', $params)
                ->withAttribute('application', $this);

            // Эцсийн handler - Closure эсвэл Controller/action гүйцэтгэнэ
            $finalHandler = function (ServerRequestInterface $request) use ($callable, $params): ResponseInterface {
                /**
                 * 1) Closure route
                 * Жишээ: $router->GET('/hello', function($req) { ... });
                 */
                if ($callable instanceof \Closure) {
                    $response = \call_user_func_array($callable, [$request]);

                /**
                 * 2) Controller/action route
                 * Жишээ: $router->GET('/user/{id}', [UserController::class, 'show']);
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
                    // Жишээ: /user/{int:id} -> UserController::show(int $id)
                    $response = \call_user_func_array([$controller, $action], $params);
                }

                // ResponseInterface биш тохиолдолд бүртгэсэн prototype-оос clone хийж буцаана.
                // clone - PSR-7 хариу immutable тул хариу бүрт цэвэр instance өгөхийн тулд.
                return $response instanceof ResponseInterface
                    ? $response
                    : clone $this->responsePrototype;
            };

            // Per-route middleware байхгүй бол шууд final handler-руу
            if (empty($routeMiddleware)) {
                return $finalHandler($request);
            }

            // Per-route middleware-уудыг queue болгож бэлдэх.
            // Global middleware-тэй ижил төрлүүд дэмжигдэнэ:
            //  - PSR-15 MiddlewareInterface instance
            //  - Closure ($request, $handler)
            //  - class-string (MiddlewareInterface implement хийсэн) - lazy instantiate
            $routeQueue = [];
            foreach ($routeMiddleware as $mw) {
                if (\is_string($mw)) {
                    if (!\class_exists($mw)) {
                        throw new \InvalidArgumentException(
                            __CLASS__ . ": Per-route middleware class [$mw] does not exist"
                        );
                    }
                    $mw = new $mw();
                }

                if (!$mw instanceof MiddlewareInterface && !$mw instanceof \Closure) {
                    throw new \InvalidArgumentException(
                        __CLASS__ . ": Per-route middleware must be MiddlewareInterface or Closure, got "
                        . \get_debug_type($mw)
                    );
                }

                $routeQueue[] = $mw;
            }
            $routeQueue[] = $finalHandler;

            // Route-level middleware-уудыг тусдаа runner-аар ажиллуулна.
            // Global runner-тэй ижил PSR-15 onion model.
            $routeRunner = $this->createRunner($routeQueue);
            return $routeRunner->handle($request);
        };

        // Global runner-г үүсгээд гүйцэтгэнэ
        \reset($callbacks);
        return $this->createRunner($callbacks)->handle($request);
    }

    /**
     * Middleware queue-г PSR-15 onion model-оор ажиллуулдаг runner үүсгэнэ.
     *
     * Global болон route-level middleware chain-ийг ижилхэн механизмаар
     * боловсруулахын тулд анх дотооддоо тодорхойлсон anonymous class-г энд
     * factory болгож гаргалаа.
     *
     * Queue нь дараах төрлүүдийг агуулна:
     *  - PSR-15 MiddlewareInterface: process($request, $handler)-аар дуудна
     *  - Closure: ($request, $handler) аргументуудаар дуудна
     *  - callable: queue-ийн төгсгөлд оршдог route handler ($request)-аар дуудна
     *
     * @param array<int, MiddlewareInterface|\Closure|callable> $queue
     * @return RequestHandlerInterface
     */
    private function createRunner(array $queue): RequestHandlerInterface
    {
        return new class ($queue) implements RequestHandlerInterface {
            /**
             * @var array<int, MiddlewareInterface|\Closure|callable>
             */
            private array $_queue;

            /**
             * @param array<int, MiddlewareInterface|\Closure|callable> $queue
             */
            public function __construct(array $queue)
            {
                $this->_queue = $queue;
                \reset($this->_queue);
            }

            /**
             * Queue-н дараагийн middleware-г ажиллуулна.
             *
             * - PSR-15 MiddlewareInterface бол process() method-г дуудна
             * - Closure бол ($request, $this) аргументуудаар дуудна
             * - callable бол ($request) аргументоор дуудна (эцсийн handler)
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
                }

                // Closure middleware - ($request, $handler) signature
                if ($current instanceof \Closure) {
                    return \call_user_func_array($current, [$request, $this]);
                }

                // Эцсийн handler callable - ($request)
                return $current($request);
            }
        };
    }
}
