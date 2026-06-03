# API Documentation

**codesaur/http-application** багцын API удирдлага.

---

## Агуулга

- [Application Class](#application-class)
- [Controller Class](#controller-class)
- [ExceptionHandler Class](#exceptionhandler-class)
- [ExceptionHandlerInterface](#exceptionhandlerinterface)

---

## Application Class

**Namespace:** `codesaur\Http\Application`
**Implements:** `Psr\Http\Server\RequestHandlerInterface`

PSR-15 стандартын RequestHandlerInterface-г хэрэгжүүлсэн HTTP Application цөм класс.

### Тайлбар

Энэ класс нь HTTP хүсэлтүүдийг дараалсан middleware-ээр дамжуулж, олон Router-ийн дунд таарах route хайж, Controller/action эсвэл Closure-ийг ажиллуулж PSR-7 Response буцаах үндсэн цөм модуль юм.

**Үндсэн үүргүүд:**
- Олон Router-ийг агуулах ба нэгтгэн ажиллуулах (multi-router delegation)
- Application-ийг URL prefix-д mount хийх
- Global middleware стек удирдах (PSR-15 Middleware болон Closure)
- Хүсэлтийг бүх router-ээр дамжуулж first-added-wins зарчмаар тааруулах
- Өмнө бүртгэсэн route-ийг зориудаар дарж бичих explicit override lane (`override()`)
- Per-route middleware ажиллуулах (Router::middleware([...]))
- Controller/action эсвэл Closure route ажиллуулах
- Handler ResponseInterface буцаахгүй бол constructor-оор өгсөн хариуны prototype-оос clone хийж fallback болгох

### Properties

#### `private array $routers`

use()-ээр бүртгэлтэй ердийн Router instance-уудын жагсаалт. Зөвхөн use() дотроос л set хийгддэг тул private.

use(RouterInterface)-ээр нэмэгдсэн дарааллаар хадгалагдана. Эрэмбэ - match/generate/pattern гэх мэт lookup-д first-added-wins: эхэлж нэмэгдсэн router ялна - предиктабл, санамсаргүй shadowing-гүй. Route-ийг зориудаар дарж бичихийг хүсвэл `$overrides` lane ашиглана.

#### `private array $overrides`

Override lane - өмнө бүртгэсэн route-ийг зориудаар дарж бичих, override()-ээр бүртгэлтэй Router-уудын жагсаалт. Зөвхөн override() дотроос л set хийгддэг тул private.

match/generate/pattern/getRoutes бүгдэд `$routers`-ээс **өмнө** шалгагдах тул эдгээрийн route нь бүртгэх дарааллаас үл хамааран ялна. Override-ийг далд (registration order-ийн санамсаргүй гаж нөлөө) бус ил, зориудын үйлдэл болгоно - explicit override best practice. Lane дотроо first-added-wins.

#### `private string $mountPath`

Application-ийн mount path (URL prefix).

Хоосон бол ('') Application нь root-д сууж байна. Утгатай үед (жишээ: '/dashboard') Application нь тухайн path-д mount хийгдсэн.

#### `private ResponseInterface $responsePrototype`

Fallback хариуны prototype. Controller/action эсвэл Closure нь ResponseInterface биш төрөл буцаавал `handle()` энэ prototype-оос clone хийж хүчинтэй хариу үүсгэнэ. Constructor-оор дамждаг тул энэ багц аль нэг тодорхой PSR-7 implementation-д тулгуурлахгүй.

#### `private array $middlewares`

Global middleware жагсаалт. Дараах төрлүүдийг хүлээн авна:
- PSR-15 MiddlewareInterface
- Closure middleware ($request, $handler)

### Methods

#### `public function __construct(ResponseInterface $responsePrototype)`

Application үүсгэх.

Constructor нь нэг PSR-7 `ResponseInterface`-ийг fallback хариуны prototype болгон авна: handler ResponseInterface биш төрөл буцаавал `handle()` нь `clone $responsePrototype` буцаана. Концрет implementation-ийг гаднаас inject хийдэг тул энэ багц зөвхөн PSR-7 interface-д тулгуурлана, тодорхой PSR-7 багцад биш.

**Parameters:**
- `ResponseInterface $responsePrototype` - fallback зам дээр clone хийгдэх хариуны prototype

**Жишээ:**
```php
use codesaur\Http\Application\Application;
use codesaur\Http\Message\NonBodyResponse;

$app = new Application(new NonBodyResponse());
```

Subclass хийхдээ prototype-ийг parent constructor руу дамжуулна:
```php
use Psr\Http\Message\ResponseInterface;

class WebApplication extends Application
{
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);
        // middleware болон router-уудаа бүртгэнэ...
    }
}
```

---

#### `public function use($object): mixed|void`

Middleware, Router эсвэл ExceptionHandler бүртгэх.

Энэ метод нь дараах төрлийн объектуудыг хүлээн авна:
- **MiddlewareInterface**: PSR-15 стандартын middleware
- **Closure**: Closure middleware function
- **RouterInterface**: Router-ийг multi-router delegation-д нэмэх
- **ExceptionHandlerInterface**: Глобал exception handler бүртгэх

**Parameters:**
- `MiddlewareInterface|\Closure|RouterInterface|ExceptionHandlerInterface $object` - Бүртгэх объект

**Returns:** `mixed|void` - ExceptionHandler бүртгэх үед өмнөх handler буцаана

**Throws:**
- `\InvalidArgumentException` - Буруу төрлийн объект дамжуулсан үед

**Жишээ:**
```php
// PSR-15 Middleware
$app->use(new MyMiddleware());

// Closure middleware
$app->use(function ($req, $handler) {
    return $handler->handle($req);
});

// Router
$app->use(new ApiRouter());
$app->use(new AdminRouter());

// Exception handler
$app->use(new ExceptionHandler());
```

---

#### `public function override(RouterInterface $router): static`

Override Router бүртгэх - өмнө бүртгэсэн route-ийг зориудаар дарж бичих.

Override lane-д нэмсэн router-ууд match/generate/pattern/getRoutes бүгдэд ердийн use()-ийн router-ээс **өмнө** шалгагдана. Тиймээс тэдгээрийн route нь ердийн router-ийн ижил path-ийг бүртгэх дарааллаас үл хамааран ялна.

Энэ нь override-ийг ил, зориудын үйлдэл болгоно (explicit override best practice), зүгээр use()-ийн дарааллд найдсан далд override биш. Bootstrap уншихад override бүр тодорхой харагдана.

**Хэзээ ашиглах вэ:** override нь зөвхөн дарж бичих гэж буй route чинь vendor багц (composer dependency) дотор зарлагдсан үед л утга учиртай. Vendor доторх route-ийн кодыг developer шууд засах боломжгүй (зассан ч `composer update` дээр дарагдана) тул өөрийн Router-аар override lane-д дарж бичнэ. Харин route чинь өөрийн project дотор зарлагдсан бол шууд эх кодон дээр нь засаад болно - тийм тохиолдолд override ашиглаж шинэ Router зарлах нь ямар ч шаардлагагүй overkill.

**Parameters:**
- `RouterInterface $router` - Override хийх route-уудыг агуулсан Router

**Returns:** `static` - Fluent chain-д ашиглахын тулд `$this` буцаана

**Example:**
```php
$app->use(new ProfileRouter());         // vendor багцаас ирсэн /profile - кодыг нь засах боломжгүй

$themeProfile = new Router();
$themeProfile->GET('/profile', [ThemeProfileController::class, 'show'])->name('profile');
$app->override($themeProfile);          // ил override - энэ ялна
```

---

#### `public function mount(string $prefix): static`

Application-ийг URL prefix-д mount хийнэ.

Mount хийгдсэний дараа Router-уудын бүх route нь автоматаар энэ prefix-д хадгалагдсан гэж тооцогдоно. Router-ууд өөрсдөө mount path-ийг мэдэхгүй - reusable байна.

**Parameters:**
- `string $prefix` - URL prefix (mount point). Leading/trailing slash сонголтот.

**Returns:** `static` - Fluent chain-д ашиглахын тулд `$this` буцаана

**Жишээ:**
```php
$app = (new Application(new NonBodyResponse()))->mount('/dashboard');
// Router-д GET('/users') гэж бүртгэсэн route нь /dashboard/users-д сонгогдоно
// generate('users') нь '/dashboard/users' буцаана

// Prefix normalization:
$app->mount('/dashboard');   // -> '/dashboard'
$app->mount('dashboard');    // -> '/dashboard'
$app->mount('/dashboard/');  // -> '/dashboard'
$app->mount('');             // -> '' (mount-гүй)
$app->mount('/');            // -> '' (mount-гүй)
```

---

#### `public function getMountPath(): string`

Одоогийн mount path-ийг буцаах (introspection).

**Returns:** `string` - Mount хийгээгүй бол '' (хоосон), эсвэл '/dashboard' гэх мэт

---

#### `public function match(string $path, string $method): ?array`

Route match хайна - override lane эхэлж, дараа нь ердийн router-ууд (first-added-wins).

Mount path тогтоосон бол request path-аас mount prefix-ийг автоматаар зүсэж Router-уудад дамжуулна. Boundary protection: `/dashboard` нь `/dashboard`, `/dashboard/users`-д таарна гэхдээ `/dashboardx`-д таарахгүй.

**Parameters:**
- `string $path` - Хайх URL path
- `string $method` - HTTP method

**Returns:** `?array` - Таарвал `[callable, params, middleware]` tuple, үгүй бол `null`

---

#### `public function generate(string $ruleName, array $params = []): string`

Route name хайна - override lane эхэлж, дараа нь ердийн router-ууд (first-found-wins). Mount хийсэн үед л үр дүнд mount prefix авто-нэмэгдэнэ - mount заавал биш.

**Parameters:**
- `string $ruleName` - Маршрутын нэр
- `array $params` - Параметрүүд

**Returns:** `string` - Үүсгэсэн URL (mount хийсэн бол prefix-тэй)

**Throws:**
- `\OutOfRangeException` - Аль ч router-д name олдохгүй бол
- `\InvalidArgumentException` - Параметрийн төрөл буруу бол

**Жишээ:**
```php
$router->GET('/users/{int:id}', $handler)->name('user.view');
$app->use($router);

// Mount-гүй үед - route path-ийг шууд буцаана
$url = $app->generate('user.view', ['id' => 42]);
// '/users/42'

// Mount хийсэн бол prefix авто-нэмэгдэнэ
$app->mount('/dashboard');
$url = $app->generate('user.view', ['id' => 42]);
// '/dashboard/users/42'
```

---

#### `public function pattern(string $ruleName): string`

Route name-ийн filter prefix зэргийг хасч буцаах (client-side substitution-д бэлэн). Mount хийсэн үед л mount prefix авто-нэмэгдэнэ - mount заавал биш.

**Parameters:**
- `string $ruleName` - Маршрутын нэр

**Returns:** `string` - Pattern (mount хийсэн бол prefix-тэй)

**Жишээ:**
```php
$router->GET('/news/{int:id}/{slug}', $h)->name('news');
$app->use($router);

// Mount-гүй үед
$pattern = $app->pattern('news');
// '/news/{id}/{slug}'

// Mount хийсэн бол prefix авто-нэмэгдэнэ
$app->mount('/dashboard');
$pattern = $app->pattern('news');
// '/dashboard/news/{id}/{slug}'
```

---

#### `public function getRoutes(): array`

Бүх router-ийн route-уудыг нэгтгэж буцаана. Mount prefix-тэй бүтэн URL pattern буцаана. Эрэмбэ нь `match()`-тэй ижил: override lane эхэлж, дараа нь ердийн router; (pattern, method) collision дээр эхэлж олдсон ялна (override lane > ердийн router).

**Returns:** `array` - `[pattern => [method => [callable, middleware]]]`

---

#### `public function getRouters(): array`

use()-ээр бүртгэлтэй ердийн router-уудыг буцаах (introspection). use()-ийн дарааллаар жагсагдсан. Override lane-ийг getOverrides() буцаана.

**Returns:** `list<RouterInterface>`

---

#### `public function getOverrides(): array`

override()-ээр бүртгэлтэй override lane-ийн router-уудыг буцаах (introspection). Эдгээр нь ердийн router-ээс өмнө шалгагдаж өмнө бүртгэсэн route-ийг дарж бичдэг. override()-ийн дарааллаар жагсагдсан.

**Returns:** `list<RouterInterface>`

---

#### `public function handle(ServerRequestInterface $request): ResponseInterface`

PSR-15 RequestHandlerInterface::handle()-ийн хэрэгжилт.

Энэ функц нь HTTP хүсэлтийг боловсруулах бүрэн процесс-ийг гүйцэтгэнэ:

1. Global middleware queue-г бэлтгэнэ
2. Эцсийн route matcher callback-г queue-н төгсгөлд нэмнэ
3. Middleware-үүдийг дарааллаар нь ажиллуулна (onion model)
4. Application::match() дуудаж route хайна (mount prefix зүсэлт + multi-router delegation)
5. Per-route middleware-уудыг бэлдэж, эцэст нь Controller/action эсвэл Closure-г дуудаж Response үүсгэнэ
6. Response-г буцаана (ResponseInterface биш бол constructor-оор өгсөн prototype-оос clone хийж fallback болгоно)

**'application' request attribute:** Application instance өөрөө `'application'` attribute-аар request-д очино. Controller-ууд `$request->getAttribute('application')->generate($name)` дуудах үед Application::generate() дамжин mount prefix автоматаар прэпенд хийгдэнэ.

**Parameters:**
- `ServerRequestInterface $request` - PSR-7 ServerRequest объект

**Returns:** `ResponseInterface` - PSR-7 Response объект

**Throws:**
- `\Error` - Маршрут олдоогүй (404) буюу controller class байхгүй (501) үед
- `\BadMethodCallException` - Controller дотор action method байхгүй үед (501)

**Жишээ:**
```php
use codesaur\Router\Router;
use codesaur\Http\Application\Application;
use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\NonBodyResponse;

$router = new Router();
$router->GET('/hello', function ($req) {
    echo 'Hello World';
});

$app = new Application(new NonBodyResponse());
$app->use($router);

$request = (new ServerRequest())->initFromGlobal();
$response = $app->handle($request);
```

---

## Controller Class

**Namespace:** `codesaur\Http\Application`
**Type:** `abstract class`

Бүх Controller классуудын суурь анги.

### Тайлбар

Энэ класс нь PSR-7 ServerRequest объектын shortcut getter method-үүдийг агуулна. Controller-үүд энэ класс-аас удамшиж, request мэдээлэлд хялбар хандах боломжтой.

### Properties

#### `protected ServerRequestInterface $request`

Ирсэн HTTP хүсэлт (PSR-7 ServerRequest).

### Methods

#### `public function __construct(ServerRequestInterface $request)`

Controller конструктор.

Controller үүсэхэд PSR-7 ServerRequest автоматаар дамжина. Энэ request-г бүх method-үүдэд ашиглаж болно.

**Parameters:**
- `ServerRequestInterface $request` - PSR-7 ServerRequest объект

---

#### `public final function getRequest(): ServerRequestInterface`

Request объектыг авах.

**Returns:** `ServerRequestInterface`

**Жишээ:**
```php
$request = $this->getRequest();
$method = $request->getMethod();
$uri = $request->getUri()->getPath();
```

---

#### `public final function getParsedBody(): array`

POST/PUT/JSON parsed body-г буцаах.

**Returns:** `array<string, mixed>`

**Жишээ:**
```php
$data = $this->getParsedBody();
$name = $data['name'] ?? 'Unknown';
```

---

#### `public final function getQueryParams(): array`

Query string параметрүүдийг авах.

**Returns:** `array<string, mixed>`

**Жишээ:**
```php
$params = $this->getQueryParams();
$page = $params['page'] ?? 1;
```

---

#### `public final function getAttributes(): array`

Бүх request attributes-г авах.

**Returns:** `array<string, mixed>`

---

#### `public final function getAttribute(string $name, $default = null): mixed`

Нэг attribute-г авах.

**Parameters:**
- `string $name` - Attribute-ийн нэр
- `mixed $default` - Attribute байхгүй бол буцаах default утга

**Returns:** `mixed`

**Жишээ:**
```php
// Route parameters авах
$params = $this->getAttribute('params');
$userId = $params['id'] ?? null;

// Application instance авах (mount-aware URL generate-д)
$app = $this->getAttribute('application');
$url = $app->generate('user.view', ['id' => $userId]);
```

> **Чухал:** `'application'` attribute нь **Application instance өөрөө** буцаана. Controller-ээс URL үүсгэхдээ `$this->getAttribute('application')->generate(...)` дуудахад mount prefix автоматаар нэмэгдэнэ.

---

## ExceptionHandler Class

**Namespace:** `codesaur\Http\Application`
**Implements:** `codesaur\Http\Application\ExceptionHandlerInterface`

Lightweight алдааны боловсруулагч. Системд гарсан аливаа Exception / Error-ийг нэг цэгээс хүлээн авч, зохих HTTP статус кодтой хариу үүсгэх.

### Тайлбар

**Үндсэн үүрэг:**
- Алдааны кодын дагуу HTTP статус тохируулах
- ReasonPhrase тогтоосон эсэхийг шалгах
- Алдааг серверийн error_log руу бичих
- Хэрэглэгчид зориулсан энгийн HTML error page үүсгэх
- CODESAUR_DEVELOPMENT = true үед trace мэдээлэл харуулах

### Methods

#### `public function exception(\Throwable $throwable): void`

Exception / Throwable боловсруулах үндсэн функц.

`Application::use(new ExceptionHandler())` гэж бүртгэгдсэн үед PHP-ийн `set_exception_handler()` механизмаар автоматаар дуудагдана.

Энэ функц нь:
1. Алдааны кодыг шалгаж HTTP статус код тохируулна
2. Алдааг error_log руу бичнэ
3. HTML error page үүсгэн хэрэглэгчид харуулна
4. Development mode дээр stack trace харуулна

**Parameters:**
- `\Throwable $throwable` - Илэрсэн Exception / Error объект

**Returns:** `void`

**Жишээ:**
```php
$app = new Application(new NonBodyResponse());
$app->use(new ExceptionHandler());

throw new \Error("Not Found", 404);
throw new \Exception("Server Error", 500);
```

**Development Mode:**
```php
define('CODESAUR_DEVELOPMENT', true);
// Одоо exception гарвал stack trace харагдана
```

---

## ExceptionHandlerInterface

**Namespace:** `codesaur\Http\Application`
**Type:** `interface`

Application түвшний алдааны боловсруулагч интерфэйс.

### Тайлбар

Энэ интерфэйсийг хэрэгжүүлсэн класс нь системд гарсан аливаа Exception / Error-ийг нэг цэгээс хүлээн авч хүссэн хэлбэрээр боловсруулах боломжтой.

`Application::use(new YourHandler())` гэж бүртгэх үед PHP-ийн `set_exception_handler()` механизмаар автоматаар дуудагддаг.

**Зориулалт:**
- Алдааны логжуулалт
- Custom error page үүсгэх
- HTTP статус код тохируулах
- Хөгжүүлэлтийн горимд stack trace харуулах

### Methods

#### `public function exception(\Throwable $throwable): void`

Гарсан Exception / Throwable-ийг боловсруулах функц.

**Parameters:**
- `\Throwable $throwable` - Илэрсэн Exception эсвэл Error объект

**Returns:** `void`

**Жишээ:**
```php
class MyCustomHandler implements ExceptionHandlerInterface
{
    public function exception(\Throwable $throwable): void
    {
        $code = $throwable->getCode() ?: 500;
        \http_response_code($code);
        \error_log($throwable->getMessage());
        echo "Error: " . $throwable->getMessage();
    }
}

$app->use(new MyCustomHandler());
```

---

## Per-route Middleware

Route-д тусгай middleware оноох (Router::middleware-аар). Дэмжих төрлүүд:
- **MiddlewareInterface instance**
- **Closure** ($request, $handler)
- **class-string** (MiddlewareInterface implement хийсэн) - lazy instantiation

```php
$router = new Router();

// MiddlewareInterface instance
$router->GET('/admin', $h)->middleware([new AuthMiddleware()]);

// class-string (lazy instantiate)
$router->POST('/save', $h)->middleware([AuthMiddleware::class, CsrfMiddleware::class]);

// Closure
$router->DELETE('/x', $h)->middleware([
    function ($req, $handler) { return $handler->handle($req); },
]);
```

**Validation:** Application::handle() дотор per-route middleware нь strict шалгагдана. Class-string байхгүй эсвэл MiddlewareInterface/Closure биш зүйл бол `\InvalidArgumentException` шиддэг.

---

## Холбоотой багцууд

- **codesaur/router** - Router функционал (шаардлагатай - `RouterInterface`-ийг өгдөг)
- **codesaur/http-message** - PSR-7 HTTP Message хэрэгжилт (санал болгосон - `ResponseInterface` prototype-ийн тохиромжтой эх сурвалж, жишээ нь `NonBodyResponse`; аль ч PSR-7 implementation ажиллана)

---

## Бүрэн жишээ

```php
use codesaur\Router\Router;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Application\Controller;
use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\NonBodyResponse;

// Router үүсгэх, route-ууд бүртгэх
$router = new Router();
$router->GET('/', [HomeController::class, 'index']);
$router->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');
$router->POST('/api/users', [UserController::class, 'create']);

// Application үүсгэх
$app = new Application(new NonBodyResponse());

// Exception handler бүртгэх
$app->use(new ExceptionHandler());

// Middleware бүртгэх
$app->use(function ($request, $handler) {
    $request = $request->withAttribute('start_time', microtime(true));
    return $handler->handle($request);
});

// Router нэмэх
$app->use($router);

// Сонголт: mount хийх
// $app->mount('/api/v1');

// Request боловсруулах
$request = (new ServerRequest())->initFromGlobal();
$response = $app->handle($request);
```

### Controller жишээ

```php
class UserController extends Controller
{
    public function show(int $id): void
    {
        $query = $this->getQueryParams();

        echo "User ID: $id";
        echo "Page: " . ($query['page'] ?? 1);
    }

    public function create(): void
    {
        $data = $this->getParsedBody();
        $name = $data['name'] ?? 'Unknown';

        // Mount-aware URL generate
        $userUrl = $this->getAttribute('application')->generate('user.show', ['id' => 1]);

        echo "Created user: $name (view: $userUrl)";
    }
}
```
