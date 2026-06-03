# Changelog

All notable changes to `codesaur/http-application` are documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [7.0.0] - 2026-06-03

Major architectural overhaul: separation of concerns, no magic API, multi-router delegation, mount feature, and PSR-7-only HTTP message coupling (the fallback response is injected via the constructor).

### Added
- **Multi-router delegation** in `Application` (in response to `Router::merge()` being removed in codesaur/router v6):
  - `Application::use(RouterInterface)` - add Routers to the Application
  - `Application::match()`, `generate()`, `pattern()`, `getRoutes()` - explicit methods that delegate across all Routers
  - `Application::getRouters()` - return the normal Routers registered via use() (introspection)
  - Order: use() registration order (first-added-wins) - the earliest-added router wins, a predictable default
  - generate/pattern: first-found-wins (silent on name collision)
  - getRoutes: aggregate, first-found-wins on (pattern, method) collision
- **Explicit route override (override lane)** in `Application`:
  - `Application::override(RouterInterface)` - add a Router that intentionally overrides a previously registered route (fluent, returns `$this`)
  - The override lane is checked before the normal routers in match/generate/pattern/getRoutes - so it wins regardless of registration order
  - `Application::getOverrides()` - return the override-lane Routers (introspection)
  - Explicit-override best practice - override is a visible, intentional action (not implicit)
- **`Application::__construct(ResponseInterface $responsePrototype)`** - the fallback response prototype is taken via the constructor. When a handler returns a non-ResponseInterface value, `handle()` returns `clone $responsePrototype`. The concrete Response is injected externally, so the package depends only on the PSR-7 interface.
- **`Application::mount(string $prefix)`** - fluent method to mount the Application at a URL prefix:
  ```php
  $router = new Router();
  $router->GET('/users', $handler);

  $app = new Application(new NonBodyResponse());
  $app->use($router);
  $app->mount('/dashboard');
  // /dashboard/users -> matches the Router's /users
  // generate('users') -> '/dashboard/users'
  ```
- **`Application::getMountPath()`** - return the configured mount path (introspection)
- Mount-aware route resolution:
  - `match()` automatically strips the mount prefix from the request path before delegating to Routers
  - `generate()`, `pattern()` automatically prepend the mount prefix to the returned URL
  - `getRoutes()` returns full URL patterns including the mount prefix
  - A path outside the mount prefix returns `null` (no route match)
  - Boundary protection: `/dashboard` does not match `/dashboardx`
- Per-route middleware strict validation: a value that is not a MiddlewareInterface, Closure, or class-string throws `InvalidArgumentException`.

### Removed
- **BREAKING**: the `__call()` magic method was removed. Shortcuts like `$app->GET(...)` no longer exist. To register routes you must create an explicit Router instance and call `use($router)`.
- **BREAKING**: the constructor no longer auto-creates a Router. The Application is created with no Router, and `getRouters()` initially returns `[]`.
- **BREAKING**: the "primary router" concept was removed. All Routers are equal - matching follows registration order only.
- **BREAKING**: codesaur/http-message was removed from `require`. The response fallback is no longer hard-wired to the concrete `NonBodyResponse` and now depends only on the PSR-7 `ResponseInterface` - the concrete implementation is chosen by the user and passed via the constructor.

### Changed
- **BREAKING**: the constructor signature changed: v6's `__construct()` (which auto-created a Router) -> `__construct(ResponseInterface $responsePrototype)`. `new Application()` and `new Application($router)` no longer work - a PSR-7 response must be passed (e.g. `new Application(new NonBodyResponse())`). Subclasses forward it via `parent::__construct($response)`.
- **BREAKING**: the `'application'` request attribute is now the Application instance itself (previously named `'router'` and a Router instance). Calling `$req->getAttribute('application')->generate($name)` from a Controller automatically prepends the mount prefix.
- **BREAKING**: the fallback for a handler returning a non-ResponseInterface value is no longer the hard-coded `new NonBodyResponse()` but `clone $responsePrototype` from the constructor-provided prototype.
- `Application::$router : RouterInterface` -> `Application::$routers : list<RouterInterface>` (internal).
- `Application::use()` now accepts RouterInterface.
- route matching inside `handle()` now calls `Application::match()` - mount prefix stripping is centralized in one place.

### Dependencies
- codesaur/router: ^5.x -> ^6.0.0
- added psr/http-message (^2.0) - ResponseInterface
- codesaur/http-message: `require` -> `suggest` + `require-dev` (used in tests and the example; not required downstream)

### Migration

V6 (before):
```php
$app = new Application();
$app->GET('/home', $handler);
```

V7 (now):
```php
use codesaur\Http\Message\NonBodyResponse;   // or any PSR-7 ResponseInterface

$router = new Router();
$router->GET('/home', $handler);
$app = new Application(new NonBodyResponse());
$app->use($router);
```

A subclass forwards the response to the parent:
```php
use Psr\Http\Message\ResponseInterface;

class WebApplication extends Application
{
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);
        // ...
    }
}
```

Controller URL generation: `$req->getAttribute('router')` -> `$req->getAttribute('application')`.

[7.0.0]: https://github.com/codesaur-php/HTTP-Application/compare/v6.0.3...v7.0.0

---

## [6.0.3] - 2026-05-18

### Changed
- codesaur/router: ^5.1.1 -> ^5.2.0
  - Adds new `pattern()` method to `RouterInterface` for client-side URL substitution
  - `pattern('news-view')` -> `/news/{id}/{slug}` (filter prefixes like `{int:}`, `{uint:}`, `{float:}`, `{utf8:}` stripped)
  - Resolves the long-standing limitation where `generate('route', ['id' => '_PLACEHOLDER_'])` would throw `InvalidArgumentException` for typed parameters
  - Purely additive change: `match()`, `merge()`, `generate()` behavior unchanged - no source code adaptation required in this package

[6.0.3]: https://github.com/codesaur-php/HTTP-Application/compare/v6.0.2...v6.0.3

---

## [6.0.2] - 2026-03-19

### Changed
- codesaur/http-message: ^3.0.1 -> ^3.0.2
  - Fixed PSR-7 header bug: `ServerRequest::initFromGlobal()` `getallheaders()` loop was only storing headers in `$this->serverParams` but not registering them in `$this->headers`
  - This caused `getHeaderLine()`, `getHeader()`, `hasHeader()` to return empty for all headers except `Host`
  - Now all headers (`X-CSRF-TOKEN`, `Content-Type`, `Accept`, etc.) are correctly accessible via PSR-7 standard methods

[6.0.2]: https://github.com/codesaur-php/HTTP-Application/compare/v6.0.1...v6.0.2

---

## [6.0.1] - 2026-03-06

### Changed
- codesaur/router: ^5.0.0 -> ^5.1.1
- codesaur/http-message: ^3.0.0 -> ^3.0.1

[6.0.1]: https://github.com/codesaur-php/HTTP-Application/compare/v6.0.0...v6.0.1

---

## [6.0.0] - 2026-01-08

### Added
- English documentation (docs/en/README.md, api.md, review.md)
- Composer test commands: test, test:unit, test:integration, test:coverage, test:coverage-clover
- CONTRIBUTING.md with development workflow, coding guidelines, PR templates

### Changed
- README.md reorganized: Mongolian first, English second, bilingual comments
- Documentation structure improved across all language versions
- codesaur/router: >=4.0 -> ^5.0.0
- codesaur/http-message: >=1.3 -> ^3.0.0
- psr/http-server-middleware: >=1.0.1 -> ^1.0.2

### Fixed
- Repository URLs and project references in documentation
- Package name references throughout documentation

[6.0.0]: https://github.com/codesaur-php/HTTP-Application/compare/v5.0...v6.0.0

---

## [5.0] - 2021-10-06

### Added
- RouterInterface support in use() for router merging
- Router instance added to request attributes
- Enhanced route parameter handling with path normalization

### Changed
- Route parameters attribute: 'param' -> 'params'
- Response fallback: Response -> NonBodyResponse
- ExceptionHandler trace: print_r() -> var_dump()
- codesaur/router: >=3.1 -> >=4.0
- codesaur/http-message: >=1.2 -> >=1.3

### Fixed
- Path normalization for subdirectory installations
- Empty target path handling (normalized to '/')

[5.0]: https://github.com/codesaur-php/HTTP-Application/compare/v4.0...v5.0

---

## [4.0] - 2021-09-29

### Added
- Full PSR-15 MiddlewareInterface support
- Closure-based middleware support
- Onion model middleware chain (before -> handler -> after)
- Router merge via use() method
- Controller methods: getQueryParams(), getAttributes(), getAttribute()
- ExceptionHandler development mode with stack trace (CODESAUR_DEVELOPMENT)
- Route parameters stored as array in 'param' attribute

### Changed
- Removed codesaur/globals dependency
- Controller $request property: private -> protected
- getPostParam() now uses native filter_input() instead of codesaur/globals
- codesaur/router: >=1.0 -> >=3.1
- codesaur/http-message: >=1.0 -> >=1.2
- ExceptionHandler error title format improved
- Application use() handles MiddlewareInterface, Closure, RouterInterface, ExceptionHandlerInterface

### Removed
- codesaur/globals package dependency
- Controller dependency on codesaur\Globals\Post

[4.0]: https://github.com/codesaur-php/HTTP-Application/compare/v1.0...v4.0

---

## [1.0] - 2021-03-15

### Added
- Initial release
- Application class implementing PSR-15 RequestHandlerInterface
- Basic codesaur/router integration
- Abstract Controller with methods: getRequest(), getParsedBody(), getBodyParam(), getQueryParam(), getPostParam()
- ExceptionHandler with HTTP status code support
- ExceptionHandlerInterface for custom handlers
- Closure and Controller/action route support
- Route parameters as individual request attributes

### Dependencies
- PHP >=7.2.0
- codesaur/globals >=1.0
- codesaur/router >=1.0
- codesaur/http-message >=1.0
- psr/http-server-middleware >=1.0.1

[1.0]: https://github.com/codesaur-php/HTTP-Application/releases/tag/v1.0
