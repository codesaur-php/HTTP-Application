# 📘 API Documentation

**codesaur/http-application** багцын API удирдлага.

---

## 📋 Агуулга

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

Энэ класс нь HTTP хүсэлтүүдийг дараалсан middleware-ээр дамжуулж, маршрутын callback эсвэл Controller->action-г ажиллуулж PSR-7 Response буцаах үндсэн цөм модуль юм.

**Үндсэн үүргүүд:**
- Router үүсгэх, маршрут бүртгэх
- Middleware стек удирдах (PSR-15 Middleware болон Closure)
- Хүсэлтийг маршрутад тохируулах (Router::match)
- Controller/action эсвэл Closure route ажиллуулах
- Response багцын NonBodyResponse-г хэрэглэж fallback хариу буцаах

### Properties

#### `protected RouterInterface $router`

Application дотор ашиглагдах үндсэн Router instance.

Энэ нь бүх route pattern, method mapping, параметр тайлбарлалтыг гүйцэтгэнэ. Router-ийн бүх public method-үүдийг Application-оор шууд дуудах боломжтой.

#### `private array $_middlewares`

Middleware жагсаалт (queue).

Дараах төрлүүдийг хүлээн авна:
- PSR-15 MiddlewareInterface
- Closure middleware ($request, $handler)
- RouterInterface (merge хийнэ)
- ExceptionHandlerInterface (глобал exception handler болгоно)

### Methods

#### `public function __construct()`

Application конструктор.

Application үүсэх үед шинэ Router instance автоматаар үүснэ. Энэ Router-г ашиглан маршрутуудыг бүртгэж болно.

**Жишээ:**
```php
$app = new Application();
```

---

#### `public function __call(string $name, array $arguments): mixed`

Router-ийн аливаа public method-ийг Application-оор шууд дуудах боломж олгоно.

Энэ нь magic method бөгөөд Router классын бүх public method-үүдийг Application instance-оор шууд дуудах боломжийг олгодог.

**Parameters:**
- `string $name` - Дуудах функцийн нэр (жишээ: GET, POST, PUT, DELETE гэх мэт)
- `array<int, mixed> $arguments` - Аргументууд

**Returns:** `mixed` - Router method-ийн буцаах утга

**Жишээ:**
```php
$app->GET('/home', fn($req) => echo 'Home');
$app->POST('/api/users', [UserController::class, 'create']);
$app->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');
```

---

#### `public function use($object): mixed|void`

Middleware, Router эсвэл ExceptionHandler бүртгэх.

Энэ метод нь дараах төрлийн объектуудыг хүлээн авна:
- **MiddlewareInterface**: PSR-15 стандартын middleware
- **Closure**: Closure middleware function
- **RouterInterface**: Өөр router-ийн маршрутуудыг нэгтгэх
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

// Exception handler
$app->use(new ExceptionHandler());

// Router merge
$app->use(new CustomRouter());
```

---

#### `public function handle(ServerRequestInterface $request): ResponseInterface`

PSR-15 RequestHandlerInterface::handle()-ийн хэрэгжилт.

Энэ функц нь HTTP хүсэлтийг боловсруулах бүрэн процесс-ийг гүйцэтгэнэ:

1. Middleware queue-г бэлтгэнэ
2. Эцсийн route matcher callback-г queue-н төгсгөлд нэмнэ
3. Middleware-үүдийг дарааллаар нь ажиллуулна (onion model)
4. Тохирох маршрут олдох юм бол Controller/action эсвэл Closure-г дуудаж Response үүсгэнэ
5. Response-г буцаана (ResponseInterface биш бол NonBodyResponse fallback)

**Route Matching:**
- URI path болон HTTP method-оор маршрут олно
- Route parameters-г Request attributes-д нэмнэ (жишээ: `/user/{int:id}` → `$request->getAttribute('params')['id']`)
- Router instance-г Request attribute-д нэмнэ (`$request->getAttribute('router')`)

**Route Execution:**
- Closure route: `$app->GET('/hello', function($req) { ... })`
- Controller/action route: `$app->GET('/user/{id}', [UserController::class, 'show'])`
- Route parameters автоматаар action method-ийн аргумент болгон дамжуулна

**Parameters:**
- `ServerRequestInterface $request` - PSR-7 ServerRequest объект

**Returns:** `ResponseInterface` - PSR-7 Response объект

**Throws:**
- `\Error` - Маршрут олдоогүй (404) буюу controller class байхгүй (501) үед
- `\BadMethodCallException` - Controller дотор action method байхгүй үед (501)

**Жишээ:**
```php
use codesaur\Http\Message\ServerRequest;

$app = new Application();
$app->GET('/hello', function ($req) {
    echo 'Hello World';
});

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

Controller-ийн бүх method-үүдэд PSR-7 ServerRequest объектод хандах боломж олгоно.

**Returns:** `ServerRequestInterface` - PSR-7 ServerRequest объект

**Жишээ:**
```php
$request = $this->getRequest();
$method = $request->getMethod(); // GET, POST, PUT, DELETE, etc.
$uri = $request->getUri()->getPath(); // /user/123
```

---

#### `public final function getParsedBody(): array`

POST/PUT/JSON parsed body-г буцаах.

Request body нь JSON эсвэл form-urlencoded байвал парс хийгдсэн массив буцаана. Null бол хоосон массив буцаана.

**Returns:** `array<string, mixed>` - Parsed body массив

**Жишээ:**
```php
$data = $this->getParsedBody();
$name = $data['name'] ?? 'Unknown';
$email = $data['email'] ?? '';
```

---

#### `public final function getQueryParams(): array`

Query string параметрүүдийг авах.

URL-ийн query string-ээс параметрүүдийг авна.  
Жишээ: `?page=1&limit=10` → `['page' => '1', 'limit' => '10']`

**Returns:** `array<string, mixed>` - Query параметрүүдийн массив

**Жишээ:**
```php
$params = $this->getQueryParams();
$page = $params['page'] ?? 1;
$limit = $params['limit'] ?? 10;
```

---

#### `public final function getAttributes(): array`

Бүх request attributes-г авах.

Attributes нь route parameters, router instance, middleware-ээс нэмсэн custom attributes зэрэг байж болно.

**Returns:** `array<string, mixed>` - Бүх attributes-ийн массив

**Жишээ:**
```php
$attrs = $this->getAttributes();
$params = $attrs['params'] ?? [];
$router = $attrs['router'] ?? null;
```

---

#### `public final function getAttribute(string $name, $default = null): mixed`

Нэг attribute-г авах.

Request attributes нь route parameters, router instance, middleware-ээс нэмсэн custom attributes зэрэг байж болно.

**Parameters:**
- `string $name` - Attribute-ийн нэр
- `mixed $default` - Attribute байхгүй бол буцаах default утга

**Returns:** `mixed` - Attribute-ийн утга эсвэл default утга

**Жишээ:**
```php
// Route parameters авах
$params = $this->getAttribute('params');
$userId = $params['id'] ?? null;

// Router instance авах
$router = $this->getAttribute('router');

// Middleware-ээс нэмсэн custom attribute
$startTime = $this->getAttribute('start_time', 0);
```

---

## ExceptionHandler Class

**Namespace:** `codesaur\Http\Application`  
**Implements:** `codesaur\Http\Application\ExceptionHandlerInterface`

Энэ класс нь ExceptionHandlerInterface-ийг хэрэгжүүлж, системд гарсан аливаа Exception / Error-ийг нэг цэгээс хүлээн авч, зохих HTTP статус кодтой хариу үүсгэх зориулалттай, lightweight алдааны боловсруулагч юм.

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

Application::use(new ExceptionHandler()) гэж бүртгэгдсэн үед PHP-ийн set_exception_handler() механизмаар автоматаар дуудагдана.

Энэ функц нь:
1. Алдааны кодыг шалгаж HTTP статус код тохируулна
   - Exception/Error-ийн `getCode()` нь HTTP статус код байвал ReasonPhrase class-д тодорхойлогдсон эсэхийг шалгаж, зөв бол `http_response_code()` дуудаж HTTP загварыг тохируулна
2. Алдааг error_log руу бичнэ
3. HTML error page үүсгэн хэрэглэгчид харуулна
4. Development mode дээр stack trace харуулна

**Parameters:**
- `\Throwable $throwable` - Илэрсэн Exception / Error объект

**Returns:** `void`

**Жишээ:**
```php
// Application-д бүртгэх
$app = new Application();
$app->use(new ExceptionHandler());

// Алдаа гаргах
throw new \Error("Not Found", 404);
throw new \Exception("Server Error", 500);
```

**Development Mode:**
```php
define('CODESAUR_DEVELOPMENT', true);
// Одоо exception гарвал stack trace харагдана
```

---

#### `private function getHost(): string`

HTTP host URL-г тодорхойлох.

HTTPS эсвэл HTTP протоколыг автоматаар тодорхойлж, host name-ийг нэгтгэн буцаана.

Протоколыг дараах байдлаар тодорхойлно:
- `$_SERVER['HTTPS']` байгаа бөгөөд 'off' биш бол HTTPS
- `$_SERVER['SERVER_PORT'] == 443` бол HTTPS
- Бусад тохиолдолд HTTP

**Returns:** `string` - Protocol + host (жишээ: https://example.com, http://localhost)

---

## ExceptionHandlerInterface

**Namespace:** `codesaur\Http\Application`  
**Type:** `interface`

Application түвшний алдааны боловсруулагч интерфэйс.

### Тайлбар

Энэ интерфэйсийг хэрэгжүүлсэн класс нь системд гарсан аливаа Exception / Error-ийг нэг цэгээс хүлээн авч хүссэн хэлбэрээр боловсруулах боломжтой болно.

Application::use(new ExceptionHandler()) гэж бүртгэх үед PHP-ийн set_exception_handler() механизмаар автоматаар дуудагддаг.

**Зориулалт:**
- Алдааны логжуулалт
- Custom error page үүсгэх
- HTTP статус код тохируулах
- Хөгжүүлэлтийн горимд stack trace харуулах

### Methods

#### `public function exception(\Throwable $throwable): void`

Гарсан Exception / Throwable-ийг боловсруулах функц.

Энэ функц нь системд гарсан аливаа алдааг хүлээн авч, HTTP статус код тохируулах, лог бичих, error page үүсгэх зэрэг боловсруулалт хийх үүрэгтэй.

**Parameters:**
- `\Throwable $throwable` - Илэрсэн Exception эсвэл Error объект

**Returns:** `void`

**Жишээ:**
```php
class MyCustomHandler implements ExceptionHandlerInterface
{
    public function exception(\Throwable $throwable): void
    {
        // HTTP статус код тохируулах
        $code = $throwable->getCode() ?: 500;
        \http_response_code($code);

        // Лог бичих
        \error_log($throwable->getMessage());

        // Error page харуулах
        echo "Error: " . $throwable->getMessage();
    }
}

$app->use(new MyCustomHandler());
```

---

## 🔗 Холбоотой багцууд

- **codesaur/router** - Router функционал
- **codesaur/http-message** - PSR-7 HTTP Message хэрэгжилт

---

## 📝 Жишээ

### Бүрэн жишээ

```php
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Application\Controller;
use codesaur\Http\Message\ServerRequest;

// Application үүсгэх
$app = new Application();

// Exception handler бүртгэх
$app->use(new ExceptionHandler());

// Middleware бүртгэх
$app->use(function ($request, $handler) {
    $request = $request->withAttribute('start_time', microtime(true));
    return $handler->handle($request);
});

// Route бүртгэх
$app->GET('/', [HomeController::class, 'index']);
$app->GET('/user/{int:id}', [UserController::class, 'show']);
$app->POST('/api/users', [UserController::class, 'create']);

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
        $params = $this->getAttribute('params');
        $query = $this->getQueryParams();
        
        echo "User ID: $id";
        echo "Page: " . ($query['page'] ?? 1);
    }
    
    public function create(): void
    {
        $data = $this->getParsedBody();
        $name = $data['name'] ?? 'Unknown';
        
        echo "Created user: $name";
    }
}
```
