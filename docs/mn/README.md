# codesaur/http-application

**PSR-7 & PSR-15 нийцсэн хөнгөн, уян хатан HTTP Application цөм**

---

`codesaur/http-application` нь PSR-7 (HTTP Message) ба PSR-15 (HTTP Server RequestHandler/Middleware) стандартууд дээр суурилсан **минималист**, **өндөр уян хатан**, **middleware суурьтай** Application цөм юм.

Та хүсвэл:
- Олон Router-ийг нэг Application-д нэгтгэх
- Application-ийг URL prefix-д mount хийх
- Middleware удирдах (global + per-route)
- Controller/action ашиглах
- Closure route ашиглах
- Exception handler бүртгэх
- Custom request attributes ашиглах

гэх мэтээр өөрийн хүссэн бүтэцтэй web application-ийг хэдхэн мөр кодоор босгох боломжтой.

---

# Гол боломжууд

### PSR-7 стандартын ServerRequest + Response
Request болон Response объектууд бүгд **immutable**, бүрэн стандартын дагуу.

### PSR-15 Middleware & RequestHandler гинжин бүтэц
Middleware-үүд onion model-оор (before -> action -> after) ажиллана. Global болон per-route аль алинд адил механизм.

### Multi-router delegation
Олон Router instance-ийг нэг Application-д нэгтгэж болно. use() дарааллаар match хайгдана (first-added-wins) - предиктабл бөгөөд best practice-ийг дагасан. Өмнө бүртгэсэн route-ийг шинэ router дээр зориудаар дарж бичихийг хүсвэл explicit `override()` lane ашиглана (доор үз).

### Mount feature
Application-ийг URL prefix-д суулгаж Router-ууд prefix-ийг мэдэхгүй (reusable) болгох.

### Controller суурь класс
Controller/action хэв маягаар route бичихэд тохиромжтой (сонголтот - Closure route-оор controller-гүй ч ажиллана).

### Per-route middleware
Тухайн route-д л ажиллах middleware-уудыг Router::middleware()-ээр оноох. MiddlewareInterface, Closure, class-string бүгд дэмжигдэнэ.

### Exception Handler
Алдааны боловсруулалт. Development mode дээр trace харуулдаг. Хөгжүүлэгч өөрийн хүссэнээр сайжруулж болно.

### Цэвэр separation of concerns
Magic API байхгүй - route бүртгэлт Router-ийн л хариуцлага. Application нь зөвхөн coordinator.

---

# Суулгах

```
composer require codesaur/http-application
```

---

# Архитектур

```
Application
 +-- Middleware stack (PSR-15 + Closure)
 +-- Router collection (list of RouterInterface)
 |    +-- Router #1
 |    +-- Router #2
 |    +-- ...
 +-- Mount path (optional URL prefix)
 +-- ExceptionHandler
 +-- Controller / Closure route executor
```

**Request flow:**
```
Request
  -> Global middleware chain (PSR-15 onion model)
  -> Application::match() [mount prefix strip]
  -> Override lane, дараа нь эхэлж таарсан Router ялна (first-added-wins)
  -> Per-route middleware chain
  -> Controller/action эсвэл Closure
  -> Response
```

---

# Хэрэглээний жишээ

## 1. Энгийн setup (нэг Router)

```php
use codesaur\Router\Router;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\NonBodyResponse;

// Route-уудаа Router дээр бүртгэнэ
$router = new Router();
$router->GET('/', function ($req) {
    echo 'Hello World!';
});

// Application үүсгээд router, middleware нэмнэ
$app = new Application(new NonBodyResponse());
$app->use(new ExceptionHandler());
$app->use($router);

// Request handle хийнэ
$app->handle((new ServerRequest())->initFromGlobal());
```

## 2. Application-ийг extend хийх pattern

```php
use Psr\Http\Message\ResponseInterface;

class WebApplication extends Application
{
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);   // fallback хариуны prototype-ийг parent руу дамжуулна

        $this->use(new ExceptionHandler());
        $this->use(new SessionMiddleware());

        // Модулийн router-уудыг нэмэх
        $this->use(new HomeRouter());
        $this->use(new ShopRouter());
        $this->use(new BlogRouter());
    }
}

$app = new WebApplication(new NonBodyResponse());
$app->handle((new ServerRequest())->initFromGlobal());
```

## 3. Mount feature - Application-ийг URL prefix-д суулгах

```php
// Router-ууд prefix-ийг МЭДЭХГҮЙ - reusable
$adminRouter = new Router();
$adminRouter->GET('/users', [UserAdmin::class, 'list'])->name('users');
$adminRouter->GET('/posts', [PostAdmin::class, 'list'])->name('posts');

// Application-ийг entry point дээр mount хийнэ
$app = new Application(new NonBodyResponse());
$app->use($adminRouter);
$app->mount('/dashboard');

// /dashboard/users -> Router-д /users-аар тааран ажиллана
// generate('users') -> '/dashboard/users'
// $app->mount('/admin') гэвэл нэг ч route өөрчилөхгүйгээр /admin-руу шилжинэ
```

Mount хийсний дараа Controller-ээс `$req->getAttribute('application')->generate('name')` дуудахад mount prefix автоматаар нэмэгдэнэ.

## 4. Multi-router - олон Router нэгтгэх

```php
$apiRouter = new Router();
$apiRouter->GET('/api/users', [UserApi::class, 'list'])->name('api.users');

$adminRouter = new Router();
$adminRouter->GET('/admin/dashboard', [Admin::class, 'index'])->name('admin.dash');

$homeRouter = new Router();
$homeRouter->GET('/', $homeHandler);

$app = new Application(new NonBodyResponse());
$app->use($apiRouter);
$app->use($adminRouter);
$app->use($homeRouter);

// Match order: use() дараалал (first-added-wins) - предиктабл default
// generate()/pattern() бүх router дээр first-found-wins хайна
$url = $app->generate('api.users');     // '/api/users'
```

### Route override

Өмнө бүртгэсэн route-ийг шинэ router дээр зориудаар дарж бичихдээ explicit `override()` lane ашиглана. Override router-ууд ердийн `use()` router-ээс **өмнө** шалгагдах тул тэдгээрийн route ялна - бүртгэх дараалал хамаагүй. Энэ нь default-ийг предиктабл байлгаж (санамсаргүй shadowing-гүй), override-ийг ил, bootstrap дотор тодорхой болгоно - explicit override best practice-ийг дагасан.

**Хэзээ ашиглах вэ:** override нь зөвхөн дарж бичих гэж буй route чинь vendor багц (composer dependency) дотор зарлагдсан үед л утга учиртай. Vendor доторх route-ийн кодыг developer шууд засах боломжгүй (зассан ч `composer update` дээр дарагдана) тул өөрийн Router-аар override lane-д дарж бичнэ. Харин дарж бичих гэж буй route чинь өөрийн project дотор зарлагдсан бол түүнийг шууд эх кодон дээр нь засаж болно - тийм тохиолдолд override ашиглаж шинэ Router зарлах нь ямар ч шаардлагагүй overkill.

```php
$app->use(new ProfileRouter());         // vendor багцаас ирсэн /profile - кодыг нь засах боломжгүй

$themeProfile = new Router();
$themeProfile->GET('/profile', [ThemeProfileController::class, 'show'])->name('profile');
$app->override($themeProfile);          // ИЛ override - энэ ялна
```

---

# Router-ийн route төрлүүд

```php
$router = new Router();

// Named route + typed parameter
$router->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');

// Multi-method
$router->POST_PUT('/api/users', [UserController::class, 'save']);

// Multiple types (int, uint, float)
$router->GET('/sum/{int:a}/{uint:b}', function ($req) {
    $params = $req->getAttribute('params');
    echo $params['a'] + $params['b'];
});

// Per-route middleware
$router->POST('/admin/delete', [AdminController::class, 'delete'])
    ->middleware([AuthMiddleware::class, CsrfMiddleware::class]);
```

---

# Controller жишээ

```php
use codesaur\Http\Application\Controller;

class UserController extends Controller
{
    public function show(int $id): void
    {
        $query = $this->getQueryParams();
        $page = $query['page'] ?? 1;

        echo "User ID: $id, Page: $page";
    }

    public function create(): void
    {
        $data = $this->getParsedBody();
        $name = $data['name'] ?? 'Unknown';

        echo "Created user: $name";
    }
}
```

Controller-аас `$this->getAttribute('application')` нь Application instance-руу заана. Тиймээс `$this->getAttribute('application')->generate('user.show', ['id' => 5])` нь mount prefix-тэй URL автоматаар буцаана.

---

# Middleware жишээ (Onion model)

```php
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OnionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface
    {
        // before
        $res = $handler->handle($req);
        // after
        return $res;
    }
}

// Global middleware
$app->use(new OnionMiddleware());

// Closure middleware
$app->use(function ($req, $handler) {
    $start = microtime(true);
    $res = $handler->handle($req);
    error_log("Took: " . (microtime(true) - $start) . "s");
    return $res;
});
```

---

# Per-route middleware

Route-д тусгай middleware оноох (зөвхөн тэр route-д ажиллана):

```php
$router = new Router();

// MiddlewareInterface instance
$router->GET('/admin', [AdminCtrl::class, 'index'])
    ->middleware([new AuthMiddleware()]);

// class-string (lazy instantiate)
$router->POST('/admin/save', [AdminCtrl::class, 'save'])
    ->middleware([AuthMiddleware::class, CsrfMiddleware::class]);

// Closure
$router->DELETE('/admin/{int:id}', [AdminCtrl::class, 'delete'])
    ->middleware([
        function ($req, $handler) {
            // shouldn-key middleware зөвхөн энэ route-д
            return $handler->handle($req);
        },
    ]);
```

---

# Алдааны боловсруулалт (ExceptionHandler)

```php
$app->use(new ExceptionHandler());
```

- Алдааны код байвал HTTP статус автоматаар тохируулна
- Алдааг `error_log` руу бичнэ
- HTML error page буцаана
- Development mode дээр trace харагдана

```php
define('CODESAUR_DEVELOPMENT', true); // Development mode идэвхжүүлэх
```

## Custom ExceptionHandler

```php
use codesaur\Http\Application\ExceptionHandlerInterface;

class MyHandler implements ExceptionHandlerInterface {
    public function exception(\Throwable $e) {
        http_response_code(500);
        echo "Custom error: " . $e->getMessage();
    }
}

$app->use(new MyHandler());
```

---

# Хөгжүүлэлтийн зөвлөмж

- PHP 8.2.1+ орчин
- Route бүртгэх нь Router-ийн хариуцлага - Application бол coordinator л

---

## Тест ажиллуулах

### Composer Test Command-ууд

```bash
# Бүх тест ажиллуулах (Unit + Integration)
composer test

# Зөвхөн Unit тест
composer test:unit

# Зөвхөн Integration тест
composer test:integration

# HTML coverage report
composer test:coverage

# Clover XML coverage report (CI/CD-д)
composer test:coverage-clover
```

### Тестүүдийн мэдээлэл

- **Unit Tests**: Application, Controller, ExceptionHandler классуудын тест
- **Integration Tests**: Бүх компонентуудыг хамтдаа ашиглах integration тест
- **Edge Case Tests**: Хязгаарын тохиолдлын тест (mount, multi-router, middleware validation)
- **Performance Tests**: Гүйцэтгэлийн тест

### PHPUnit шууд ашиглах

```bash
# Бүх тест
vendor/bin/phpunit

# Зөвхөн Unit
vendor/bin/phpunit --testsuite "HTTP Application Test Suite"

# Зөвхөн Integration
vendor/bin/phpunit --testsuite "Integration Tests"

# Coverage report (Clover XML)
vendor/bin/phpunit --coverage-clover coverage.xml

# HTML coverage
vendor/bin/phpunit --coverage-html coverage/html

# Тодорхой файл
vendor/bin/phpunit tests/ApplicationTest.php
```

**Windows хэрэглэгчид:** `vendor/bin/phpunit`-ийг `vendor\bin\phpunit.bat`-аар солино уу

## GitHub Actions CI/CD

Төсөл нь GitHub Actions CI/CD workflow-тэй. Push эсвэл Pull Request хийхэд автоматаар тестүүд ажиллана:

- **PHP хувилбарууд:** 8.2, 8.3, 8.4
- **Үйлдлийн системүүд:** Ubuntu, Windows, macOS
- **Coverage report:** Codecov руу автоматаар илгээгдэнэ

---

# Лиценз

Энэ төсөл MIT лицензтэй.

---

# Нэмэлт документ

- [API](api.md) - Бүх класс болон method-ийн дэлгэрэнгүй
- [REVIEW](review.md) - Код шалгалтын тайлан

---

# Зохиогч

Narankhuu
https://github.com/codesaur

---

# Дүгнэлт

`codesaur/http-application` бол:
- Хөнгөн (магик код байхгүй)
- Уян хатан (multi-router, mount, per-route middleware)
- Стандарт мөрдсөн (PSR-7, PSR-15)
- Энгийн (цэвэр separation of concerns)
- Хурдан

PHP дээр PSR стандарт нийцсэн өөрийн аппликейшн бүтэцтэй болохыг хүсвэл тохиромжтой сонголт.
