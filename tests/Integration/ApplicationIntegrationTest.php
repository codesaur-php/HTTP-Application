<?php

namespace codesaur\Http\Application\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\Uri;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\Controller;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Application\Tests\TestHelper;

/**
 * Application Integration Test
 *
 * Application-ийн бүрэн integration тестүүд.
 * Middleware chain, routing, controller, exception handling зэрэг
 * бүх компонентуудыг хамтдаа тестлэх.
 *
 * @package codesaur\Http\Application\Tests\Integration
 */
class ApplicationIntegrationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
    }

    public function testFullRequestResponseCycle(): void
    {
        $executionLog = [];

        // Middleware 1
        $this->app->use(new class($executionLog) implements MiddlewareInterface {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->log[] = 'middleware1-before';
                $response = $handler->handle($request);
                $this->log[] = 'middleware1-after';
                return $response;
            }
        });

        // Route
        $this->app->GET('/test', function ($req) use (&$executionLog) {
            $executionLog[] = 'route-executed';
            echo 'Test Response';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $response = $this->app->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals([
            'middleware1-before',
            'route-executed',
            'middleware1-after'
        ], $executionLog);
    }

    public function testControllerWithMiddleware(): void
    {
        $this->app->use(new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $request = $request->withAttribute('middleware_data', 'test-value');
                return $handler->handle($request);
            }
        });

        $this->app->GET('/user/{int:id}', [IntegrationTestController::class, 'show']);

        $request = TestHelper::createServerRequest('GET', '/user/123');

        $response = $this->app->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testExceptionHandlerIntegration(): void
    {
        $this->app->use(new ExceptionHandler());

        $this->app->GET('/error', function ($req) {
            throw new \Error('Test Error', 500);
        });

        $request = TestHelper::createServerRequest('GET', '/error');

        $this->expectException(\Error::class);
        $this->app->handle($request);
    }

    public function testMultipleMiddlewareChain(): void
    {
        $order = [];

        $this->app->use(new class($order) implements MiddlewareInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 1;
                $response = $handler->handle($request);
                $this->order[] = 6;
                return $response;
            }
        });

        $this->app->use(new class($order) implements MiddlewareInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 2;
                $response = $handler->handle($request);
                $this->order[] = 5;
                return $response;
            }
        });

        $this->app->use(new class($order) implements MiddlewareInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 3;
                $response = $handler->handle($request);
                $this->order[] = 4;
                return $response;
            }
        });

        $this->app->GET('/test', function ($req) use (&$order) {
            $order[] = 'route';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $this->app->handle($request);

        $this->assertEquals([1, 2, 3, 'route', 4, 5, 6], $order);
    }

    public function testRouteParametersInController(): void
    {
        // Router нь string type дэмжихгүй, type-гүй параметр ашиглах
        $this->app->GET('/product/{int:id}/category/{name}', [IntegrationTestController::class, 'product']);

        $request = TestHelper::createServerRequest('GET', '/product/42/category/electronics');

        $response = $this->app->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testQueryParametersWithRoute(): void
    {
        $this->app->GET('/search', [IntegrationTestController::class, 'search']);

        $uri = TestHelper::createUri('/search');
        $uri->setQuery('q=test&page=1');
        $request = TestHelper::createServerRequest('GET', $uri);
            

        $response = $this->app->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPostRequestWithBody(): void
    {
        $this->app->POST('/api/users', [IntegrationTestController::class, 'create']);

        $request = TestHelper::createServerRequest('POST', '/api/users');
        $request = $request->withParsedBody(['name' => 'John', 'email' => 'john@example.com']);
            

        $response = $this->app->handle($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}

// Test Controller for integration tests
class IntegrationTestController extends Controller
{
    public function show(int $id): void
    {
        $data = $this->getAttribute('middleware_data');
        echo "User ID: $id, Data: $data";
    }

    public function product(int $id, string $name): void
    {
        echo "Product ID: $id, Category: $name";
    }

    public function search(): void
    {
        $params = $this->getQueryParams();
        echo "Search: " . ($params['q'] ?? '');
    }

    public function create(): void
    {
        $body = $this->getParsedBody();
        echo "Created: " . ($body['name'] ?? 'Unknown');
    }
}
