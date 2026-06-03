# codesaur/http-application

**Lightweight, flexible HTTP Application core compliant with PSR-7 & PSR-15**

---

`codesaur/http-application` is a **minimalist**, **highly flexible**, **middleware-based** Application core built on PSR-7 (HTTP Message) and PSR-15 (HTTP Server RequestHandler/Middleware) standards.

You can:
- Combine multiple Routers in one Application
- Mount Application at a URL prefix
- Manage middleware (global + per-route)
- Use Controller/action pattern
- Use Closure routes
- Register Exception handler
- Use custom request attributes

and build your desired web application structure with just a few lines of code.

---

# Key Features

### PSR-7 Standard ServerRequest + Response
Request and Response objects are all **immutable** and fully compliant with the standard.

### PSR-15 Middleware & RequestHandler Chain
Middlewares operate in onion model (before -> action -> after). Same mechanism for global and per-route.

### Multi-router delegation
Combine multiple Router instances in one Application. Match order follows use() registration (first-added-wins) - predictable and follows best practice. To intentionally override a previously registered route from a new router, use the explicit `override()` lane (see below).

### Mount feature
Mount Application at a URL prefix - Routers stay prefix-naive and reusable.

### Controller base class
Suitable for controller/action style routes (optional - Closure routes work without any controller).

### Per-route middleware
Attach middlewares to specific routes via Router::middleware(). Supports MiddlewareInterface, Closure, and class-string.

### Exception Handler
Error handling. Shows stack trace in development mode. Customizable.

### Clean separation of concerns
No magic API - route registration is solely Router's responsibility. Application is just a coordinator.

---

# Installation

```
composer require codesaur/http-application
```

---

# Architecture

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
  -> Application::match() [strips mount prefix]
  -> Override lane, then first Router that matches wins (first-added-wins)
  -> Per-route middleware chain
  -> Controller/action or Closure
  -> Response
```

---

# Usage Examples

## 1. Simple setup (single Router)

```php
use codesaur\Router\Router;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\NonBodyResponse;

// Register routes on Router
$router = new Router();
$router->GET('/', function ($req) {
    echo 'Hello World!';
});

// Create Application, add router and middleware
$app = new Application(new NonBodyResponse());
$app->use(new ExceptionHandler());
$app->use($router);

// Handle request
$app->handle((new ServerRequest())->initFromGlobal());
```

## 2. Extending Application pattern

```php
use Psr\Http\Message\ResponseInterface;

class WebApplication extends Application
{
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);   // forward the fallback response prototype

        $this->use(new ExceptionHandler());
        $this->use(new SessionMiddleware());

        // Add module routers
        $this->use(new HomeRouter());
        $this->use(new ShopRouter());
        $this->use(new BlogRouter());
    }
}

$app = new WebApplication(new NonBodyResponse());
$app->handle((new ServerRequest())->initFromGlobal());
```

## 3. Mount feature - mount Application at a URL prefix

```php
// Routers DO NOT know about the prefix - reusable
$adminRouter = new Router();
$adminRouter->GET('/users', [UserAdmin::class, 'list'])->name('users');
$adminRouter->GET('/posts', [PostAdmin::class, 'list'])->name('posts');

// Mount Application at entry point
$app = new Application(new NonBodyResponse());
$app->use($adminRouter);
$app->mount('/dashboard');

// /dashboard/users -> matches Router's /users
// generate('users') -> '/dashboard/users'
// $app->mount('/admin') -> shifts to /admin without changing a single route
```

After mounting, calling `$req->getAttribute('application')->generate('name')` from a Controller automatically prepends the mount prefix.

## 4. Multi-router - combine multiple Routers

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

// Match order: use() order (first-added-wins) - predictable default
// generate()/pattern() searches all routers - first-found-wins
$url = $app->generate('api.users');     // '/api/users'
```

### Route override

Override a previously registered route intentionally via the explicit `override()` lane. Override routers are checked **before** normal `use()` routers, so their routes win - regardless of registration order. This keeps the default predictable (no accidental shadowing) while making overrides explicit and visible in the bootstrap - following the explicit-override best practice.

**When to use:** override only makes sense when the route you want to replace is declared inside a vendor package (a composer dependency). You cannot edit a vendor route's source directly (and any edit would be wiped on `composer update`), so you shadow it from the override lane with your own Router. If the route you want to change lives in your own project, just edit it at the source - reaching for override and declaring a new Router there is needless overkill.

```php
$app->use(new ProfileRouter());         // /profile coming from a vendor package - its source can't be edited

$themeProfile = new Router();
$themeProfile->GET('/profile', [ThemeProfileController::class, 'show'])->name('profile');
$app->override($themeProfile);          // explicit override - this wins
```

---

# Router route types

```php
$router = new Router();

// Named route + typed parameter
$router->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');

// Multi-method route
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

# Controller example

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

`$this->getAttribute('application')` returns the Application instance. So `$this->getAttribute('application')->generate('user.show', ['id' => 5])` automatically returns a URL with mount prefix.

---

# Middleware example (Onion model)

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

Attach middleware to a specific route (only runs for that route):

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
            return $handler->handle($req);
        },
    ]);
```

---

# Exception handling (ExceptionHandler)

```php
$app->use(new ExceptionHandler());
```

- Automatically sets HTTP status from exception code
- Writes error to `error_log`
- Returns HTML error page
- Shows trace in development mode

```php
define('CODESAUR_DEVELOPMENT', true); // Enable development mode
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

# Development tips

- PHP 8.2.1+ environment
- Route registration is Router's responsibility - Application is just a coordinator

---

## Running tests

### Composer test commands

```bash
# Run all tests (Unit + Integration)
composer test

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration

# HTML coverage report
composer test:coverage

# Clover XML coverage report (for CI/CD)
composer test:coverage-clover
```

### Test categories

- **Unit Tests**: Application, Controller, ExceptionHandler classes
- **Integration Tests**: All components working together
- **Edge Case Tests**: Boundary cases (mount, multi-router, middleware validation)
- **Performance Tests**: Performance benchmarks

### Running PHPUnit directly

```bash
# All tests
vendor/bin/phpunit

# Unit only
vendor/bin/phpunit --testsuite "HTTP Application Test Suite"

# Integration only
vendor/bin/phpunit --testsuite "Integration Tests"

# Coverage report (Clover XML)
vendor/bin/phpunit --coverage-clover coverage.xml

# HTML coverage
vendor/bin/phpunit --coverage-html coverage/html

# Specific file
vendor/bin/phpunit tests/ApplicationTest.php
```

**Windows users:** Replace `vendor/bin/phpunit` with `vendor\bin\phpunit.bat`

## GitHub Actions CI/CD

Project has a GitHub Actions CI/CD workflow. Tests run automatically on push or Pull Request:

- **PHP versions:** 8.2, 8.3, 8.4
- **Operating systems:** Ubuntu, Windows, macOS
- **Coverage report:** automatically sent to Codecov

---

# License

This project is licensed under the MIT License.

---

# Additional Documentation

- [API](api.md) - Detailed reference for all classes and methods
- [REVIEW](review.md) - Code quality review

---

# Author

Narankhuu
https://github.com/codesaur

---

# Conclusion

`codesaur/http-application` is:
- Lightweight (no magic code)
- Flexible (multi-router, mount, per-route middleware)
- Standards-compliant (PSR-7, PSR-15)
- Simple (clean separation of concerns)
- Fast

A solid choice if you want a PSR-standard PHP application core.
