# codesaur/http-application

[![CI](https://github.com/codesaur-php/HTTP-Application/workflows/CI/badge.svg)](https://github.com/codesaur-php/HTTP-Application/actions)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**PSR-7 & PSR-15 нийцсэн хөнгөн, уян хатан HTTP Application цөм**
**Lightweight, flexible HTTP Application core compliant with PSR-7 & PSR-15**

---

## Агуулга / Table of Contents

1. [Монгол](#1-монгол-тайлбар) | 2. [English](#2-english-description) | 3. [Getting Started](#3-getting-started)

---

## 1. Монгол тайлбар

`codesaur/http-application` нь PSR-7 (HTTP Message) ба PSR-15 (HTTP Server RequestHandler/Middleware) стандартууд дээр суурилсан **минималист**, **өндөр уян хатан**, **middleware суурьтай** Application цөм юм.

Та хүсвэл:
- Router ашиглах (нэг эсвэл олон)
- Middleware удирдах
- Controller/action ашиглах
- Closure route ашиглах
- Exception handler бүртгэх
- Custom request attributes ашиглах

гэх мэтээр өөрийн хүссэн бүтэцтэй web application-ийг хэдхэн мөр кодоор босгох боломжтой.

### Гол боломжууд

- PSR-7 стандартын ServerRequest + Response
- PSR-15 Middleware & RequestHandler гинжин бүтэц
- Олон Router-ийг нэг Application-д нэгтгэх (multi-router delegation)
- Application-ийг URL prefix-д mount хийх (Router-ууд reusable)
- Controller суурь класс (сонголтот - controller/action хэв маягт ашиглаж болно)
- Per-route middleware (MiddlewareInterface, Closure, class-string)
- Exception Handler (development mode-той)
- Хэт хөнгөн, хурдан - magic API байхгүй, цэвэр separation of concerns

### Дэлгэрэнгүй мэдээлэл

- [Бүрэн танилцуулга](docs/mn/README.md) - Суурилуулалт, хэрэглээ, жишээнүүд
- [API тайлбар](docs/mn/api.md) - Бүх метод, exception-үүдийн тайлбар
- [Шалгалтын тайлан](docs/mn/review.md) - Код шалгалтын тайлан

---

## 2. English Description

`codesaur/http-application` is a **minimalist**, **highly flexible**, **middleware-based** Application core built on PSR-7 (HTTP Message) and PSR-15 (HTTP Server RequestHandler/Middleware) standards.

You can:
- Use Router (one or many)
- Manage Middleware
- Use Controller/action
- Use Closure routes
- Register Exception handler
- Use Custom request attributes

and build your desired web application structure with just a few lines of code.

### Key Features

- PSR-7 Standard ServerRequest + Response
- PSR-15 Middleware & RequestHandler Chain Structure
- Multi-router delegation (combine multiple Routers in one Application)
- Mount Application at a URL prefix (Routers are reusable)
- Controller base class (optional - for controller/action style code if you want)
- Per-route middleware (MiddlewareInterface, Closure, class-string)
- Exception Handler (with development mode)
- Extremely lightweight and fast - no magic API, clean separation of concerns

### Documentation

- [Full Documentation](docs/en/README.md) - Installation, usage, examples
- [API Reference](docs/en/api.md) - Complete API documentation
- [Review](docs/en/review.md) - Complete package review and code quality assessment

---

## 3. Getting Started

### Requirements

- PHP **8.2.1+**
- Composer
- PSR-7 compatible HTTP Message implementation (e.g., `codesaur/http-message`) -
  supplies the `ServerRequestInterface` passed to `handle()` and the
  `ResponseInterface` prototype passed to the `Application` constructor

### Installation

Composer ашиглан суулгана / Install via Composer:

```bash
composer require codesaur/http-application
```

### Quick Examples

#### Application - Basic Setup

```php
use codesaur\Router\Router;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\NonBodyResponse;

// Router-д route бүртгэх / Register routes on Router
$router = new Router();
$router->GET('/', function ($req) {
    echo 'Hello World!';
});

// Application үүсгэх (fallback хариуны prototype дамжуулна) + middleware + router нэмэх
$app = new Application(new NonBodyResponse());
$app->use(new ExceptionHandler());
$app->use($router);

// Хүсэлт боловсруулах / Handle request
$request = (new ServerRequest())->initFromGlobal();
$response = $app->handle($request);
```

#### Router - Dynamic Routes

```php
$router = new Router();

// Төрөлтэй параметртэй нэртэй route
$router->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');

// Олон method-тэй route
$router->POST_PUT('/api/users', [UserController::class, 'save']);

// Параметртэй Closure route
$router->GET('/sum/{int:a}/{uint:b}', function ($req) {
    $params = $req->getAttribute('params');
    echo $params['a'] + $params['b'];
});

$app = new Application(new NonBodyResponse());
$app->use($router);
```

#### Mount - Application-ийг URL prefix-д суулгах / Mounting Application at a URL Prefix

```php
use codesaur\Router\Router;

// Router-ууд prefix-ийг мэдэхгүй - reusable
$adminRouter = new Router();
$adminRouter->GET('/users', [UserAdmin::class, 'list'])->name('users');
$adminRouter->GET('/posts', [PostAdmin::class, 'list'])->name('posts');

// Application-ийг entry point дээр mount хийнэ
$app = new Application(new NonBodyResponse());
$app->use($adminRouter);
$app->mount('/dashboard');  // бүх route /dashboard prefix-тэй болов

// match: /dashboard/users -> Router-д /users-аар тааран ажиллана
// generate('users') -> '/dashboard/users'
// $app->mount('/admin') гэвэл нэг ч route өөрчлөхгүйгээр /admin-руу шилжинэ
```

Mount хийсний дараа `$req->getAttribute('application')->generate('name')` нь Application-руу заана учир Controller-ээс URL үүсгэхэд mount prefix автоматаар нэмэгдэнэ.

#### Multi-Router - Олон Router нэгтгэх / Combining Multiple Routers

```php
use codesaur\Router\Router;

// Module бүрд өөрийн router-тэй
$apiRouter = new Router();
$apiRouter->GET('/api/users', [UserApi::class, 'list'])->name('api.users');

$adminRouter = new Router();
$adminRouter->GET('/admin/dashboard', [Admin::class, 'index'])->name('admin.dash');

$homeRouter = new Router();
$homeRouter->GET('/', $homeHandler);

// Application-д бүгдийг нэгтгэх
$app = new Application(new NonBodyResponse());
$app->use($apiRouter);
$app->use($adminRouter);
$app->use($homeRouter);

// Match order: use() дарааллаар (first-added-wins) - предиктабл default
// generate()/pattern() бүх router дээр first-found-wins хайна
$url = $app->generate('api.users');     // '/api/users'
```

#### Route override - өмнө бүртгэсэн route-ийг зориудаар дарж бичих

```php
// Router бүртгэх
$app->use(new ProfileRouter());         // /profile -> ProfileController

// Developer-ийн theme controller-руу шилжүүлэх override
$themeProfile = new Router();
$themeProfile->GET('/profile', [ThemeProfileController::class, 'show'])->name('profile');

$app->override($themeProfile);          // ил override - энэ ялна

// Override lane нь ердийн router-ээс өмнө шалгагдана. Зүгээр use()-ийн
// дараалалд найдаж далд override хийхгүй - bootstrap дотор ил харагдана.
```

#### Controller - controller/action route

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

#### Middleware - PSR-15 & Closure

```php
// PSR-15 Middleware
class AuthMiddleware implements MiddlewareInterface
{
    public function process($req, $handler): ResponseInterface
    {
        // Баталгаажуулалт шалгах / Check authentication
        if (!$this->isAuthenticated($req)) {
            return new Response(401);
        }

        return $handler->handle($req);
    }
}

$app->use(new AuthMiddleware());

// Closure Middleware
$app->use(function ($req, $handler) {
    $startTime = microtime(true);
    $response = $handler->handle($req);
    $duration = microtime(true) - $startTime;

    error_log("Request took: {$duration}s");
    return $response;
});
```

### Running Tests

Тест ажиллуулах / Run tests:

```bash
# Бүх тестүүдийг ажиллуулах / Run all tests
composer test

# Зөвхөн unit тест / Unit tests only
composer test:unit

# Зөвхөн integration тест / Integration tests only
composer test:integration

# Coverage-тэй тест ажиллуулах / Run tests with coverage
composer test:coverage
```

---

## Architecture

```
Application
 +-- Middleware stack (PSR-15 + Closure)
 +-- Override lane (list of RouterInterface)     <- checked first, explicit override()
 |    +-- Override Router #1
 |    +-- ...
 +-- Router collection (list of RouterInterface) <- normal use(), first-added-wins
 |    +-- Router #1
 |    +-- Router #2
 |    +-- ...
 +-- Mount path (optional URL prefix)
 +-- ExceptionHandler
 +-- Controller / Closure route executor
```

**Request Flow:**
```
Request
  -> Global middleware chain (PSR-15 onion model)
  -> Application::match() [strips mount prefix]
  -> Override lane, then first Router that matches wins (first-added-wins)
  -> Per-route middleware chain (PSR-15 onion model)
  -> Controller/action OR Closure
  -> Response
```

**URL Generation:** `Application::generate('name')` -> finds name in first Router that has it -> prepends mount prefix -> returns URL.

---

## Changelog

- [CHANGELOG.md](CHANGELOG.md) - Full version history

## Contributing & Security

- [Contributing Guide](.github/CONTRIBUTING.md)
- [Security Policy](.github/SECURITY.md)

## License

This project is licensed under the MIT License.

## Author

**Narankhuu**  
codesaur@gmail.com  
https://github.com/codesaur  

**codesaur ecosystem:** https://codesaur.net
