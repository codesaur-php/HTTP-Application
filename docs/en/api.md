# API Documentation

**codesaur/http-application** package API reference.

---

## Contents

- [Application Class](#application-class)
- [Controller Class](#controller-class)
- [ExceptionHandler Class](#exceptionhandler-class)
- [ExceptionHandlerInterface](#exceptionhandlerinterface)

---

## Application Class

**Namespace:** `codesaur\Http\Application`
**Implements:** `Psr\Http\Server\RequestHandlerInterface`

HTTP Application core class implementing PSR-15 standard RequestHandlerInterface.

### Description

This class is the core module that processes HTTP requests through a middleware chain and executes route callbacks or Controller->action to return a PSR-7 Response.

**Core functions:**
- Create Router, register routes
- Manage Middleware stack (PSR-15 Middleware and Closure)
- Match request to route (Router::match)
- Execute Controller/action or Closure route
- Use Response package's NonBodyResponse as fallback response

### Properties

#### `protected RouterInterface $router`

Main Router instance used within Application.

This handles all route pattern, method mapping, and parameter parsing. All public methods of Router can be called directly through Application.

#### `private array $_middlewares`

Middleware list (queue).

Accepts the following types:
- PSR-15 MiddlewareInterface
- Closure middleware ($request, $handler)
- RouterInterface (merge)
- ExceptionHandlerInterface (set as global exception handler)

### Methods

#### `public function __construct()`

Application constructor.

When Application is created, a new Router instance is automatically created. You can register routes using this Router.

**Example:**
```php
$app = new Application();
```

---

#### `public function __call(string $name, array $arguments): mixed`

Allows calling any public method of Router directly through Application.

This is a magic method that allows calling all public methods of Router class directly through Application instance.

**Parameters:**
- `string $name` - Function name to call (e.g., GET, POST, PUT, DELETE, etc.)
- `array<int, mixed> $arguments` - Arguments

**Returns:** `mixed` - Return value of Router method

**Example:**
```php
$app->GET('/home', fn($req) => echo 'Home');
$app->POST('/api/users', [UserController::class, 'create']);
$app->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');
```

---

#### `public function use($object): mixed|void`

Register Middleware, Router, or ExceptionHandler.

This method accepts the following object types:
- **MiddlewareInterface**: PSR-15 standard middleware
- **Closure**: Closure middleware function
- **RouterInterface**: Merge routes from another router
- **ExceptionHandlerInterface**: Register global exception handler

**Parameters:**
- `MiddlewareInterface|\Closure|RouterInterface|ExceptionHandlerInterface $object` - Object to register

**Returns:** `mixed|void` - Returns previous handler when registering ExceptionHandler

**Throws:**
- `\InvalidArgumentException` - When wrong type of object is passed

**Example:**
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

Implementation of PSR-15 RequestHandlerInterface::handle().

This function performs the complete process of handling HTTP requests:

1. Prepares middleware queue
2. Adds final route matcher callback to the end of queue
3. Executes middleware in sequence (onion model)
4. If matching route is found, calls Controller/action or Closure and generates Response
5. Returns Response (NonBodyResponse fallback if not ResponseInterface)

**Route Matching:**
- Finds route by URI path and HTTP method
- Adds route parameters to Request attributes (e.g., `/user/{int:id}` -> `$request->getAttribute('params')['id']`)
- Adds Router instance to Request attribute (`$request->getAttribute('router')`)

**Route Execution:**
- Closure route: `$app->GET('/hello', function($req) { ... })`
- Controller/action route: `$app->GET('/user/{id}', [UserController::class, 'show'])`
- Route parameters are automatically passed as action method arguments

**Parameters:**
- `ServerRequestInterface $request` - PSR-7 ServerRequest object

**Returns:** `ResponseInterface` - PSR-7 Response object

**Throws:**
- `\Error` - When route not found (404) or controller class doesn't exist (501)
- `\BadMethodCallException` - When action method doesn't exist in controller (501)

**Example:**
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

Base class for all Controller classes.

### Description

This class contains shortcut getter methods for PSR-7 ServerRequest object. Controllers extend this class and can easily access request information.

### Properties

#### `protected ServerRequestInterface $request`

Incoming HTTP request (PSR-7 ServerRequest).

### Methods

#### `public function __construct(ServerRequestInterface $request)`

Controller constructor.

When Controller is created, PSR-7 ServerRequest is automatically passed. This request can be used in all methods.

**Parameters:**
- `ServerRequestInterface $request` - PSR-7 ServerRequest object

---

#### `public final function getRequest(): ServerRequestInterface`

Get Request object.

Provides access to PSR-7 ServerRequest object in all Controller methods.

**Returns:** `ServerRequestInterface` - PSR-7 ServerRequest object

**Example:**
```php
$request = $this->getRequest();
$method = $request->getMethod(); // GET, POST, PUT, DELETE, etc.
$uri = $request->getUri()->getPath(); // /user/123
```

---

#### `public final function getParsedBody(): array`

Return POST/PUT/JSON parsed body.

If request body is JSON or form-urlencoded, returns parsed array. Returns empty array if null.

**Returns:** `array<string, mixed>` - Parsed body array

**Example:**
```php
$data = $this->getParsedBody();
$name = $data['name'] ?? 'Unknown';
$email = $data['email'] ?? '';
```

---

#### `public final function getQueryParams(): array`

Get query string parameters.

Gets parameters from URL query string.
Example: `?page=1&limit=10` -> `['page' => '1', 'limit' => '10']`

**Returns:** `array<string, mixed>` - Query parameters array

**Example:**
```php
$params = $this->getQueryParams();
$page = $params['page'] ?? 1;
$limit = $params['limit'] ?? 10;
```

---

#### `public final function getAttributes(): array`

Get all request attributes.

Attributes can include route parameters, router instance, custom attributes added by middleware, etc.

**Returns:** `array<string, mixed>` - All attributes array

**Example:**
```php
$attrs = $this->getAttributes();
$params = $attrs['params'] ?? [];
$router = $attrs['router'] ?? null;
```

---

#### `public final function getAttribute(string $name, $default = null): mixed`

Get one attribute.

Request attributes can include route parameters, router instance, custom attributes added by middleware, etc.

**Parameters:**
- `string $name` - Attribute name
- `mixed $default` - Default value to return if attribute doesn't exist

**Returns:** `mixed` - Attribute value or default value

**Example:**
```php
// Get route parameters
$params = $this->getAttribute('params');
$userId = $params['id'] ?? null;

// Get Router instance
$router = $this->getAttribute('router');

// Get custom attribute added by middleware
$startTime = $this->getAttribute('start_time', 0);
```

---

## ExceptionHandler Class

**Namespace:** `codesaur\Http\Application`
**Implements:** `codesaur\Http\Application\ExceptionHandlerInterface`

This class implements ExceptionHandlerInterface and is a lightweight error handler designed to catch any Exception/Error from the system and generate appropriate HTTP status code response.

### Description

**Core functions:**
- Set HTTP status based on error code
- Check if ReasonPhrase is set
- Write error to server error_log
- Generate simple HTML error page for users
- Show trace information when CODESAUR_DEVELOPMENT = true

### Methods

#### `public function exception(\Throwable $throwable): void`

Main function to handle Exception / Throwable.

When registered with Application::use(new ExceptionHandler()), it is automatically called through PHP's set_exception_handler() mechanism.

This function:
1. Checks error code and sets HTTP status code
   - If Exception/Error's `getCode()` is an HTTP status code, checks if ReasonPhrase is defined in class, and if correct, calls `http_response_code()` to set HTTP header
2. Writes error to error_log
3. Generates HTML error page and displays to user
4. Shows stack trace in development mode

**Parameters:**
- `\Throwable $throwable` - Thrown Exception / Error object

**Returns:** `void`

**Example:**
```php
// Register in Application
$app = new Application();
$app->use(new ExceptionHandler());

// Throw error
throw new \Error("Not Found", 404);
throw new \Exception("Server Error", 500);
```

**Development Mode:**
```php
define('CODESAUR_DEVELOPMENT', true);
// Now when exception occurs, stack trace will be shown
```

---

#### `private function getHost(): string`

Determine HTTP host URL.

Automatically determines HTTPS or HTTP protocol and combines with host name.

Protocol is determined as follows:
- If `$_SERVER['HTTPS']` exists and is not 'off', then HTTPS
- If `$_SERVER['SERVER_PORT'] == 443`, then HTTPS
- Otherwise HTTP

**Returns:** `string` - Protocol + host (e.g., https://example.com, http://localhost)

---

## ExceptionHandlerInterface

**Namespace:** `codesaur\Http\Application`
**Type:** `interface`

Application-level error handler interface.

### Description

Classes implementing this interface can catch any Exception/Error from the system at a single point and process it in the desired format.

When registered with Application::use(new ExceptionHandler()), it is automatically called through PHP's set_exception_handler() mechanism.

**Purpose:**
- Error logging
- Generate custom error page
- Set HTTP status code
- Show stack trace in development mode

### Methods

#### `public function exception(\Throwable $throwable): void`

Function to handle thrown Exception / Throwable.

This function is responsible for receiving any error from the system and processing it, such as setting HTTP status code, writing logs, generating error page, etc.

**Parameters:**
- `\Throwable $throwable` - Thrown Exception or Error object

**Returns:** `void`

**Example:**
```php
class MyCustomHandler implements ExceptionHandlerInterface
{
    public function exception(\Throwable $throwable): void
    {
        // Set HTTP status code
        $code = $throwable->getCode() ?: 500;
        \http_response_code($code);

        // Write log
        \error_log($throwable->getMessage());

        // Display error page
        echo "Error: " . $throwable->getMessage();
    }
}

$app->use(new MyCustomHandler());
```

---

## Related Packages

- **codesaur/router** - Router functionality
- **codesaur/http-message** - PSR-7 HTTP Message implementation

---

## Examples

### Complete Example

```php
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Application\Controller;
use codesaur\Http\Message\ServerRequest;

// Create Application
$app = new Application();

// Register exception handler
$app->use(new ExceptionHandler());

// Register middleware
$app->use(function ($request, $handler) {
    $request = $request->withAttribute('start_time', microtime(true));
    return $handler->handle($request);
});

// Register routes
$app->GET('/', [HomeController::class, 'index']);
$app->GET('/user/{int:id}', [UserController::class, 'show']);
$app->POST('/api/users', [UserController::class, 'create']);

// Process request
$request = (new ServerRequest())->initFromGlobal();
$response = $app->handle($request);
```

### Controller Example

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
