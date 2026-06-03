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

PSR-15 compliant HTTP Application core class implementing `RequestHandlerInterface`.

### Description

This class passes HTTP requests through a sequence of middlewares, finds a matching route across multiple Routers, and executes Controller/action or Closure to return a PSR-7 Response.

**Core responsibilities:**
- Hold and coordinate multiple Routers (multi-router delegation)
- Mount Application at a URL prefix
- Manage global middleware stack (PSR-15 Middleware and Closure)
- Pass request through all routers using first-added-wins matching
- Intentionally override a previously registered route via an explicit override lane (`override()`)
- Execute per-route middleware (Router::middleware([...]))
- Execute Controller/action or Closure route
- Fall back to a clone of the constructor-provided response prototype when a handler does not return a ResponseInterface

### Properties

#### `private array $routers`

List of normal Router instances registered via `use()`. Set only inside `use()`, hence private.

Stored in `use()` registration order. Order for match/generate/pattern lookups follows first-added-wins: the earliest-added router wins - predictable, no accidental shadowing. To intentionally override a route, use the `$overrides` lane.

#### `private array $overrides`

Override lane - Router instances registered via `override()` to intentionally override a previously registered route. Set only inside `override()`, hence private.

Checked **before** `$routers` in match/generate/pattern/getRoutes, so their routes win regardless of registration order. Makes override an explicit, visible action rather than an implicit side-effect of registration order - the explicit-override best practice. First-added-wins within the lane.

#### `private string $mountPath`

Application mount path (URL prefix).

When empty (''), Application is mounted at root. When set (e.g., '/dashboard'), Application is mounted at that path.

#### `private ResponseInterface $responsePrototype`

Fallback response prototype. When a Controller/action or Closure returns something other than a ResponseInterface, `handle()` clones this prototype to produce a valid response. Supplied via the constructor, so this package does not depend on any concrete PSR-7 implementation.

#### `private array $middlewares`

Global middleware list. Accepts:
- PSR-15 MiddlewareInterface
- Closure middleware ($request, $handler)

### Methods

#### `public function __construct(ResponseInterface $responsePrototype)`

Create an Application.

The constructor takes a single PSR-7 `ResponseInterface` used as the fallback response prototype: when a handler returns something other than a ResponseInterface, `handle()` returns `clone $responsePrototype`. Because the concrete implementation is injected, this package depends only on the PSR-7 interface, not on any specific PSR-7 package.

**Parameters:**
- `ResponseInterface $responsePrototype` - response prototype cloned for the fallback path

**Example:**
```php
use codesaur\Http\Application\Application;
use codesaur\Http\Message\NonBodyResponse;

$app = new Application(new NonBodyResponse());
```

When subclassing, forward the prototype to the parent constructor:
```php
use Psr\Http\Message\ResponseInterface;

class WebApplication extends Application
{
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);
        // register middleware and routers...
    }
}
```

---

#### `public function use($object): mixed|void`

Register middleware, Router, or ExceptionHandler.

Accepts the following object types:
- **MiddlewareInterface**: PSR-15 standard middleware
- **Closure**: Closure middleware function
- **RouterInterface**: Router for multi-router delegation
- **ExceptionHandlerInterface**: Global exception handler

**Parameters:**
- `MiddlewareInterface|\Closure|RouterInterface|ExceptionHandlerInterface $object`

**Returns:** `mixed|void` - returns previous handler when registering ExceptionHandler

**Throws:**
- `\InvalidArgumentException` - when passing an unsupported object type

**Example:**
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

Register an override Router - intentionally override a previously registered route.

Routers added to the override lane are checked **before** the normal `use()` routers in match/generate/pattern/getRoutes, so their routes win over a normal router's identical path - regardless of registration order.

This makes override an explicit, visible action (the explicit-override best practice) instead of relying on registration order for an implicit override. Reading the bootstrap makes every override obvious.

**When to use:** override only makes sense when the route you want to replace is declared inside a vendor package (a composer dependency). You cannot edit a vendor route's source directly (and any edit would be wiped on `composer update`), so you shadow it from the override lane with your own Router. If the route lives in your own project, just edit it at the source - reaching for override and declaring a new Router there is needless overkill.

**Parameters:**
- `RouterInterface $router` - Router holding the routes that should win

**Returns:** `static` - returns `$this` for fluent chaining

**Example:**
```php
$app->use(new ProfileRouter());         // /profile coming from a vendor package - its source can't be edited

$themeProfile = new Router();
$themeProfile->GET('/profile', [ThemeProfileController::class, 'show'])->name('profile');
$app->override($themeProfile);          // explicit override - this wins
```

---

#### `public function mount(string $prefix): static`

Mount Application at a URL prefix.

After mounting, all Routers' routes are automatically treated as living under this prefix. Routers themselves don't know about the mount path - they remain reusable.

**Parameters:**
- `string $prefix` - URL prefix (mount point). Leading/trailing slashes are optional.

**Returns:** `static` - returns `$this` for fluent chaining

**Example:**
```php
$app = (new Application(new NonBodyResponse()))->mount('/dashboard');
// Router's GET('/users') becomes accessible at /dashboard/users
// generate('users') returns '/dashboard/users'

// Prefix normalization:
$app->mount('/dashboard');   // -> '/dashboard'
$app->mount('dashboard');    // -> '/dashboard'
$app->mount('/dashboard/');  // -> '/dashboard'
$app->mount('');             // -> '' (no mount)
$app->mount('/');            // -> '' (no mount)
```

---

#### `public function getMountPath(): string`

Returns the current mount path (introspection).

**Returns:** `string` - empty '' if not mounted, otherwise something like '/dashboard'

---

#### `public function match(string $path, string $method): ?array`

Searches for a match - override lane first, then normal routers (first-added-wins).

When mount path is set, the mount prefix is automatically stripped from the request path before passing to Routers. Boundary protection: `/dashboard` matches `/dashboard` and `/dashboard/users` but NOT `/dashboardx`.

**Parameters:**
- `string $path` - URL path to match
- `string $method` - HTTP method

**Returns:** `?array` - tuple `[callable, params, middleware]` if matched, `null` otherwise

---

#### `public function generate(string $ruleName, array $params = []): string`

Searches for a route name - override lane first, then normal routers (first-found-wins). The mount prefix is automatically prepended only when the Application is mounted - mounting is optional.

**Parameters:**
- `string $ruleName` - Route name
- `array $params` - Parameters

**Returns:** `string` - generated URL (with mount prefix if mounted)

**Throws:**
- `\OutOfRangeException` - when name not found in any router
- `\InvalidArgumentException` - when parameter type is wrong

**Example:**
```php
$router->GET('/users/{int:id}', $handler)->name('user.view');
$app->use($router);

// Without mount - returns the route path as-is
$url = $app->generate('user.view', ['id' => 42]);
// '/users/42'

// With mount - the prefix is prepended automatically
$app->mount('/dashboard');
$url = $app->generate('user.view', ['id' => 42]);
// '/dashboard/users/42'
```

---

#### `public function pattern(string $ruleName): string`

Returns route name pattern with filter prefixes stripped (ready for client-side substitution). The mount prefix is automatically prepended only when the Application is mounted - mounting is optional.

**Parameters:**
- `string $ruleName` - Route name

**Returns:** `string` - pattern (with mount prefix if mounted)

**Example:**
```php
$router->GET('/news/{int:id}/{slug}', $h)->name('news');
$app->use($router);

// Without mount
$pattern = $app->pattern('news');
// '/news/{id}/{slug}'

// With mount - the prefix is prepended automatically
$app->mount('/dashboard');
$pattern = $app->pattern('news');
// '/dashboard/news/{id}/{slug}'
```

---

#### `public function getRoutes(): array`

Returns all routes from all routers, merged. Returns full URL patterns including mount prefix. Order matches `match()`: override lane first, then normal routers; on (pattern, method) collision the first-found wins (override lane beats normal routers).

**Returns:** `array` - `[pattern => [method => [callable, middleware]]]`

---

#### `public function getRouters(): array`

Returns the normal routers registered via `use()` (introspection). Listed in `use()` order. The override lane is returned by `getOverrides()`.

**Returns:** `list<RouterInterface>`

---

#### `public function getOverrides(): array`

Returns the override-lane routers registered via `override()` (introspection). These are checked before the normal routers and override a previously registered route. Listed in `override()` order.

**Returns:** `list<RouterInterface>`

---

#### `public function handle(ServerRequestInterface $request): ResponseInterface`

PSR-15 RequestHandlerInterface::handle() implementation.

This function performs the full HTTP request processing pipeline:

1. Prepares the global middleware queue
2. Appends the final route matcher callback to the queue
3. Runs middlewares in sequence (onion model)
4. Calls Application::match() to find a route (mount prefix stripping + multi-router delegation)
5. Prepares per-route middleware and executes Controller/action or Closure to produce a Response
6. Returns the Response (clone of the constructor-provided prototype as fallback if not a ResponseInterface)

**'application' request attribute:** The Application instance itself is set as the 'application' attribute on the request. When Controllers call `$request->getAttribute('application')->generate($name)`, this delegates to Application::generate() which automatically prepends the mount prefix.

**Parameters:**
- `ServerRequestInterface $request` - PSR-7 ServerRequest object

**Returns:** `ResponseInterface` - PSR-7 Response object

**Throws:**
- `\Error` - route not found (404) or controller class missing (501)
- `\BadMethodCallException` - action method missing in controller (501)

**Example:**
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

Base class for all Controller classes.

### Description

This class contains shortcut getter methods for PSR-7 ServerRequest. Controllers extend this class to easily access request information.

### Properties

#### `protected ServerRequestInterface $request`

Incoming HTTP request (PSR-7 ServerRequest).

### Methods

#### `public function __construct(ServerRequestInterface $request)`

Controller constructor.

PSR-7 ServerRequest is automatically passed when Controller is constructed. This request is then accessible from all methods.

**Parameters:**
- `ServerRequestInterface $request` - PSR-7 ServerRequest object

---

#### `public final function getRequest(): ServerRequestInterface`

Get the request object.

**Returns:** `ServerRequestInterface`

**Example:**
```php
$request = $this->getRequest();
$method = $request->getMethod();
$uri = $request->getUri()->getPath();
```

---

#### `public final function getParsedBody(): array`

Get parsed POST/PUT/JSON body.

**Returns:** `array<string, mixed>`

**Example:**
```php
$data = $this->getParsedBody();
$name = $data['name'] ?? 'Unknown';
```

---

#### `public final function getQueryParams(): array`

Get query string parameters.

**Returns:** `array<string, mixed>`

**Example:**
```php
$params = $this->getQueryParams();
$page = $params['page'] ?? 1;
```

---

#### `public final function getAttributes(): array`

Get all request attributes.

**Returns:** `array<string, mixed>`

---

#### `public final function getAttribute(string $name, $default = null): mixed`

Get a single attribute.

**Parameters:**
- `string $name` - Attribute name
- `mixed $default` - Default value if attribute is absent

**Returns:** `mixed`

**Example:**
```php
// Get route parameters
$params = $this->getAttribute('params');
$userId = $params['id'] ?? null;

// Get Application instance (for mount-aware URL generation)
$app = $this->getAttribute('application');
$url = $app->generate('user.view', ['id' => $userId]);
```

> **Important:** The `'application'` attribute returns the **Application instance itself**. When generating URLs from a Controller, calling `$this->getAttribute('application')->generate(...)` automatically includes the mount prefix.

---

## ExceptionHandler Class

**Namespace:** `codesaur\Http\Application`
**Implements:** `codesaur\Http\Application\ExceptionHandlerInterface`

Lightweight exception handler. Catches any Exception/Error thrown in the system at a single point and produces a response with the appropriate HTTP status code.

### Description

**Core responsibilities:**
- Set HTTP status code based on error code
- Verify status against ReasonPhrase
- Write error to `error_log`
- Produce a simple HTML error page for the user
- Show trace information when `CODESAUR_DEVELOPMENT = true`

### Methods

#### `public function exception(\Throwable $throwable): void`

Main function to handle Exception/Throwable.

Automatically called via PHP's `set_exception_handler()` mechanism when registered via `Application::use(new ExceptionHandler())`.

This function:
1. Checks error code and sets HTTP status
2. Writes error to error_log
3. Produces HTML error page for the user
4. Shows stack trace in development mode

**Parameters:**
- `\Throwable $throwable` - the thrown Exception/Error object

**Returns:** `void`

**Example:**
```php
$app = new Application(new NonBodyResponse());
$app->use(new ExceptionHandler());

throw new \Error("Not Found", 404);
throw new \Exception("Server Error", 500);
```

**Development Mode:**
```php
define('CODESAUR_DEVELOPMENT', true);
// Stack trace will now appear when exceptions are thrown
```

---

## ExceptionHandlerInterface

**Namespace:** `codesaur\Http\Application`
**Type:** `interface`

Application-level exception handler interface.

### Description

Classes implementing this interface can catch any Exception/Error thrown in the system at a single point and process it in any way.

Automatically called via PHP's `set_exception_handler()` mechanism when registered via `Application::use(new YourHandler())`.

**Use cases:**
- Error logging
- Custom error page generation
- HTTP status code configuration
- Stack trace display in development mode

### Methods

#### `public function exception(\Throwable $throwable): void`

Function to process the thrown Exception/Throwable.

**Parameters:**
- `\Throwable $throwable` - the thrown Exception or Error object

**Returns:** `void`

**Example:**
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

Attach middleware to specific routes (via Router::middleware). Supported types:
- **MiddlewareInterface instance**
- **Closure** ($request, $handler)
- **class-string** (must implement MiddlewareInterface) - lazy instantiation

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

**Validation:** Per-route middleware is strictly validated in Application::handle(). If a class-string doesn't exist, or if a value is neither MiddlewareInterface nor Closure, an `\InvalidArgumentException` is thrown.

---

## Related packages

- **codesaur/router** - Router functionality (required - provides `RouterInterface`)
- **codesaur/http-message** - PSR-7 HTTP Message implementation (suggested - a convenient source for the `ResponseInterface` prototype, e.g. `NonBodyResponse`; any PSR-7 implementation works)

---

## Full example

```php
use codesaur\Router\Router;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Application\Controller;
use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\NonBodyResponse;

// Create router and register routes
$router = new Router();
$router->GET('/', [HomeController::class, 'index']);
$router->GET('/user/{int:id}', [UserController::class, 'show'])->name('user.show');
$router->POST('/api/users', [UserController::class, 'create']);

// Create Application
$app = new Application(new NonBodyResponse());

// Register exception handler
$app->use(new ExceptionHandler());

// Register middleware
$app->use(function ($request, $handler) {
    $request = $request->withAttribute('start_time', microtime(true));
    return $handler->handle($request);
});

// Add router
$app->use($router);

// Optional: mount at a prefix
// $app->mount('/api/v1');

// Process request
$request = (new ServerRequest())->initFromGlobal();
$response = $app->handle($request);
```

### Controller example

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

        // Mount-aware URL generation
        $userUrl = $this->getAttribute('application')->generate('user.show', ['id' => 1]);

        echo "Created user: $name (view: $userUrl)";
    }
}
```
