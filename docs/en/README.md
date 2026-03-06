# codesaur/http-application

**Lightweight, flexible HTTP Application core compliant with PSR-7 & PSR-15**

---

`codesaur/http-application` is a **minimalist**, **highly flexible**, **middleware-based** Application core built on PSR-7 (HTTP Message) and PSR-15 (HTTP Server RequestHandler/Middleware) standards.

You can:
- Add Router
- Manage Middleware
- Use Controller/action
- Use Closure routes
- Register Exception handler
- Use Custom request attributes

and build your desired web application structure with just a few lines of code.

---

# Key Features

### PSR-7 Standard ServerRequest + Response
Request and Response objects are all **immutable**, fully compliant with the standard.

### PSR-15 Middleware & RequestHandler Chain Structure
Middleware works like an onion (before -> action -> after).

### Flexible Router Integration
The package directly supports **codesaur/router**.

Dynamic, typed, multi-method routes can be easily declared.

### Controller Base Class
Suitable for PHP MVC pattern development.

### Exception Handler
Error handling. Shows trace in development mode. Developers can customize as needed.

### Extremely Lightweight and Fast
Can be used as a foundation for any framework.

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
 +-- Router (codesaur/router)
 +-- ExceptionHandler
 +-- Controller / Closure route executor
```

Application -> Middleware -> Match route -> Controller/action/Closure -> Response

---

# Usage Examples

## Application boot script (index.php)

```php
$application = new class extends Application {
    public function __construct() {
        parent::__construct();

        $this->use(new ExceptionHandler());
        $this->use(new BeforeMiddleware());
        $this->use(new AfterMiddleware());
        $this->use(new OnionMiddleware());
        $this->use(new ExampleRouter());

        $this->GET('/', [ExampleController::class, 'index']);
    }
};

$application->handle((new ServerRequest())->initFromGlobal());
```

---

# Router Examples

```php
$this->GET('/hello/{firstname}', [ExampleController::class, 'hello'])->name('hi');

$this->POST_PUT('/post-or-put', [ExampleController::class, 'post_put']);

$this->GET('/float/{float:number}', [ExampleController::class, 'float']);

$this->GET('/sum/{int:a}/{uint:b}', function ($req) {
    $a = $req->getAttribute('params')['a'];
    $b = $req->getAttribute('params')['b'];
    echo "$a + $b = " . ($a + $b);
});
```

---

# Controller Example

```php
class ExampleController extends Controller
{
    public function hello(string $firstname)
    {
        $user = $firstname;

        $params = $this->getQueryParams();
        if (!empty($params['lastname'])) {
            $user .= " {$params['lastname']}";
        }

        echo "Hello $user!";
    }
}
```

---

# Middleware Example (Onion Model)

### BeforeMiddleware -> add new attribute to request
### AfterMiddleware -> print request time
### OnionMiddleware -> print before/after log

```php
class OnionMiddleware implements MiddlewareInterface
{
    public function process($req, $handler): ResponseInterface
    {
        var_dump("i'm onion before");
        $res = $handler->handle($req);
        var_dump("i'm onion after");
        return $res;
    }
}
```

---

# Error Handling (ExceptionHandler)

```php
$this->use(new ExceptionHandler());
```

- Automatically sets HTTP status if error code exists
- Writes error to `error_log`
- Returns HTML error page
- Shows trace in development mode

```php
define('CODESAUR_DEVELOPMENT', true); // Enable development mode
```

---

# Request Processing Flow

1. Middleware stack is called from the beginning
2. Router -> Match -> Callback/Controller action
3. Middleware stack completes on return
4. Response is sent to the client

---

# Using Custom ExceptionHandler

```php
class MyHandler implements ExceptionHandlerInterface {
    public function exception(Throwable $e) {
        http_response_code(500);
        echo "Custom error: " . $e->getMessage();
    }
}

$app->use(new MyHandler());
```

---

# Development Recommendations

- PHP 8.2.1+ environment
- Apache + .htaccess rewrite configuration (optional)
- Very suitable for MVC pattern in your project

---

## Running Tests

### Composer Test Commands

```bash
# Run all tests (Unit + Integration tests)
composer test

# Run only Unit tests
composer test:unit

# Run only Integration tests
composer test:integration

# Generate HTML coverage report (in coverage/html directory)
composer test:coverage

# Generate Clover XML coverage report (for CI/CD)
composer test:coverage-clover
```

### Test Information

- **Unit Tests**: Tests for Application, Controller, ExceptionHandler classes
- **Integration Tests**: Integration tests using all components together
- **Edge Case Tests**: Edge case scenario tests
- **Performance Tests**: Performance tests

### Using PHPUnit Directly

Instead of Composer commands, you can run PHPUnit directly:

```bash
# Run all tests
vendor/bin/phpunit

# Run only Unit tests
vendor/bin/phpunit --testsuite "HTTP Application Test Suite"

# Run only Integration tests
vendor/bin/phpunit --testsuite "Integration Tests"

# Coverage report (Clover XML format)
vendor/bin/phpunit --coverage-clover coverage.xml

# Coverage report (HTML format)
vendor/bin/phpunit --coverage-html coverage/html

# Run specific test file
vendor/bin/phpunit tests/ApplicationTest.php
```

**Windows users:** Replace `vendor/bin/phpunit` with `vendor\bin\phpunit.bat`

## GitHub Actions CI/CD

The project includes GitHub Actions CI/CD workflow. Tests run automatically on push or Pull Request:

- **PHP versions:** 8.2, 8.3, 8.4
- **Operating systems:** Ubuntu, Windows, macOS
- **Coverage report:** Automatically sent to Codecov

---

# License

This project is licensed under MIT.

---

# Additional Documentation

- [API](api.md) - Complete API reference, detailed description of all classes and methods (generated by Cursor AI from PHPDoc comments)
- [REVIEW](review.md) - Code review report, code quality, architecture, PSR standards (analyzed by Cursor AI)

---

# Author

Narankhuu  
https://github.com/codesaur

---

# Conclusion

`codesaur/http-application` is:
- Lightweight
- Flexible
- Standards compliant
- Simple
- Fast

It's the perfect choice if you want to build your own application structure compliant with PSR standards on PHP!
