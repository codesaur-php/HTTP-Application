# Changelog

All notable changes to `codesaur/http-application` are documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
