# 🦖 codesaur/http-application

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
- Router нэмэх  
- Middleware удирдах  
- Controller/action ашиглах  
- Closure route ашиглах  
- Exception handler бүртгэх  
- Custom request attributes ашиглах  

гэх мэтээр өөрийн хүссэн бүтэцтэй web application-ийг хэдхэн мөр кодоор босгох боломжтой.

### Гол боломжууд

- ✔ PSR-7 стандартын ServerRequest + Response  
- ✔ PSR-15 Middleware & RequestHandler гинжин бүтэц  
- ✔ Уян хатан Router интеграци (codesaur/router)  
- ✔ Controller суурь класс (MVC хэв маяг дэмжлэг)  
- ✔ Exception Handler (development mode-той)  
- ✔ Хэт хөнгөн, хурдан  

### Дэлгэрэнгүй мэдээлэл

- 📖 [Бүрэн танилцуулга](docs/mn/README.md) - Суурилуулалт, хэрэглээ, жишээнүүд
- 📚 [API тайлбар](docs/mn/api.md) - Бүх метод, exception-үүдийн тайлбар
- 🔍 [Шалгалтын тайлан](docs/mn/review.md) - Код шалгалтын тайлан

---

## 2. English Description

`codesaur/http-application` is a **minimalist**, **highly flexible**, **middleware-based** Application core built on PSR-7 (HTTP Message) and PSR-15 (HTTP Server RequestHandler/Middleware) standards.

You can:
- Add Router  
- Manage Middleware  
- Use Controller/action  
- Use Closure routes  
- Register Exception handler  
- Use Custom request attributes  

and build your desired web application structure with just a few lines of code.

### Key Features

- ✔ PSR-7 Standard ServerRequest + Response  
- ✔ PSR-15 Middleware & RequestHandler Chain Structure  
- ✔ Flexible Router Integration (codesaur/router)  
- ✔ Controller Base Class (MVC pattern support)  
- ✔ Exception Handler (with development mode)  
- ✔ Extremely Lightweight and Fast  

### Documentation

- 📖 [Full Documentation](docs/en/README.md) - Installation, usage, examples
- 📚 [API Reference](docs/en/api.md) - Complete API documentation
- 🔍 [Review](docs/en/review.md) - Complete package review and code quality assessment

---

## 3. Getting Started

### Requirements

- PHP **8.2.1+**
- Composer
- PSR-7 compatible HTTP Message implementation (e.g., `codesaur/http-message`)

### Installation

Composer ашиглан суулгана / Install via Composer:

```bash
composer require codesaur/http-application
```

### Quick Examples

#### Application - Basic Setup

```php
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Message\ServerRequest;

// Application instance үүсгэх / Create Application instance
$app = new Application();

// Exception handler бүртгэх / Register exception handler
$app->use(new ExceptionHandler());

// Route бүртгэх / Register route
$app->GET('/', function ($req) {
    echo 'Hello World!';
});

// Хүсэлт боловсруулах / Handle request
$request = (new ServerRequest())->initFromGlobal();
$response = $app->handle($request);
```

#### Router - Dynamic Routes

```php
// Төрөлтэй параметртэй нэртэй route / Named route with typed parameters
$app->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');

// Олон method-тэй route / Multi-method route
$app->POST_PUT('/api/users', [UserController::class, 'save']);

// Параметртэй Closure route / Closure route with parameters
$app->GET('/sum/{int:a}/{uint:b}', function ($req) {
    $params = $req->getAttribute('params');
    echo $params['a'] + $params['b'];
});
```

#### Controller - MVC Pattern

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
 ├── Middleware stack (PSR-15 + Closure)
 ├── Router (codesaur/router)
 ├── ExceptionHandler
 └── Controller / Closure route executor
```

**Request Flow:** Application → Middleware → Match route → Controller/action/Closure → Response

---

## Changelog

- 📝 [CHANGELOG.md](CHANGELOG.md) - Full version history

## Contributing & Security

- 🤝 [Contributing Guide](.github/CONTRIBUTING.md)
- 🔐 [Security Policy](.github/SECURITY.md)

## License

This project is licensed under the MIT License.

## Author

**Narankhuu**  
📧 codesaur@gmail.com  
🌐 https://github.com/codesaur

🦖 **codesaur ecosystem:** https://codesaur.net
