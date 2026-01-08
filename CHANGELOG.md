# Changelog

This file contains all changes for all versions of the `codesaur/http-application` package.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [6.0.0] - 2025-01-08
[6.0.0]: https://github.com/codesaur-php/HTTP-Application/compare/v5.0...v6.0.0

### Added
- **Complete English Documentation**: Full translation of all documentation files in docs/en/:
  - docs/en/README.md - Complete English documentation with installation, usage, and examples
  - docs/en/api.md - Comprehensive API documentation in English
  - docs/en/review.md - Complete package review and code quality assessment in English
- **Composer Test Commands**: Added convenient test commands in composer.json:
  - `composer test` - Run all tests (Unit + Integration)
  - `composer test:unit` - Run unit tests only
  - `composer test:integration` - Run integration tests only
  - `composer test:coverage` - Generate HTML and text coverage reports
  - `composer test:coverage-clover` - Generate Clover XML coverage report for CI/CD
- **Contributing Guide**: Comprehensive CONTRIBUTING.md with:
  - Development workflow and setup instructions
  - Coding guidelines and standards
  - Pull request guidelines and templates
  - Commit message format (Conventional Commits)
  - Testing commands documentation
  - Code of conduct

### Changed
- **README.md Refactored**: 
  - Reorganized structure with Mongolian section first, English second
  - Bilingual main description (Mongolian/English)
  - Updated code examples with bilingual comments
  - Improved project structure and feature descriptions
- **Documentation Improvements**:
  - Updated test section in docs/mn/README.md with clearer composer command documentation
  - Improved documentation structure and consistency across all language versions
  - Enhanced code examples with bilingual comments for better understanding
- **Dependencies Updated**:
  - codesaur/router: >=4.0 → ^5.0.0
  - codesaur/http-message: >=1.3 → ^3.0.0
  - psr/http-server-middleware: >=1.0.1 → ^1.0.2

### Fixed
- Fixed repository URLs and project references in documentation
- Corrected package name references throughout documentation
- Improved CHANGELOG.md with accurate version history based on source code analysis

---

## [5.0] - 2021-10-06
[5.0]: https://github.com/codesaur-php/HTTP-Application/compare/v4.0...v5.0

### Added
- RouterInterface support in `use()` method for router merging
- Router instance added to request attributes (`$request->getAttribute('router')`)
- Enhanced route parameter handling with improved path normalization

### Changed
- Route parameters attribute changed from `'param'` (singular) to `'params'` (plural)
- Response fallback changed from `Response` to `NonBodyResponse`
- ExceptionHandler trace output changed from `print_r()` to `var_dump()` for better debugging
- Updated dependencies:
  - codesaur/router: >=3.1 → >=4.0
  - codesaur/http-message: >=1.2 → >=1.3

### Fixed
- Improved path normalization for subdirectory installations
- Better handling of empty target paths (normalized to '/')

---

## [4.0] - 2021-09-29
[4.0]: https://github.com/codesaur-php/HTTP-Application/compare/v1.0...v4.0

### Added
- **Middleware System**: Full PSR-15 MiddlewareInterface support
- **Closure Middleware**: Support for Closure-based middleware functions
- **Onion Model Middleware Chain**: Middleware execution in before → handler → after pattern
- **Router Merge**: Ability to merge routes from other Router instances via `use()` method
- **Controller Enhancements**:
  - `getQueryParams()` method to get all query parameters as array
  - `getAttributes()` method to get all request attributes
  - `getAttribute($name, $default)` method to get specific attribute with default value
  - `isDevelopment()` method to check development mode
- **ExceptionHandler Development Mode**: Stack trace display when `CODESAUR_DEVELOPMENT` is defined
- **Enhanced Route Parameter Handling**: Route parameters stored in `'param'` attribute as array

### Changed
- Removed dependency on `codesaur/globals` package
- Controller `$request` property changed from private to protected
- `getPostParam()` method implementation changed from using `codesaur/globals` to native PHP `filter_input()`
- Updated dependencies:
  - codesaur/router: >=1.0 → >=3.1
  - codesaur/http-message: >=1.0 → >=1.2
- ExceptionHandler error title format improved
- Application `use()` method now properly handles MiddlewareInterface, Closure, RouterInterface, and ExceptionHandlerInterface

### Removed
- `codesaur/globals` package dependency
- Controller dependency on `codesaur\Globals\Post` class

---

## [1.0] - 2021-03-15
[1.0]: https://github.com/codesaur-php/HTTP-Application/releases/tag/v1.0

### Added
- Initial release
- **Application Class**: Core HTTP Application implementing PSR-15 RequestHandlerInterface
- **Router Integration**: Basic integration with codesaur/router package
- **Controller Base Class**: Abstract Controller class with basic request handling methods:
  - `getRequest()` - Get PSR-7 ServerRequest
  - `getParsedBody()` - Get parsed request body
  - `getBodyParam($name)` - Get specific body parameter
  - `getQueryParam($name)` - Get specific query parameter
  - `getPostParam($name, $filter, $options)` - Get POST parameter with filtering
- **ExceptionHandler**: Basic exception handling with HTTP status code support
- **ExceptionHandlerInterface**: Interface for custom exception handlers
- Basic route matching and execution
- Support for Closure routes and Controller/action routes
- Route parameters passed as individual request attributes

### Dependencies
- PHP >=7.2.0
- codesaur/globals >=1.0
- codesaur/router >=1.0
- codesaur/http-message >=1.0
- psr/http-server-middleware >=1.0.1
