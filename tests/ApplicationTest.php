<?php

namespace codesaur\Http\Application\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\Uri;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Application\ExceptionHandlerInterface;
use codesaur\Router\RouterInterface;
use codesaur\Http\Application\Tests\TestHelper;

class ApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
    }

    public function testApplicationImplementsRequestHandlerInterface(): void
    {
        $this->assertInstanceOf(RequestHandlerInterface::class, $this->app);
    }

    public function testApplicationHasRouter(): void
    {
        // Test router exists by using a router method
        $this->app->GET('/test-router', function ($req) {
            return 'test';
        });
        $this->assertTrue(true); // Router method works, so router exists
    }

    public function testUseMiddlewareInterface(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $this->app->use($middleware);
        $this->assertTrue(true); // No exception thrown
    }

    public function testUseClosureMiddleware(): void
    {
        $this->app->use(function ($request, $handler) {
            return $handler->handle($request);
        });
        $this->assertTrue(true); // No exception thrown
    }

    public function testUseExceptionHandler(): void
    {
        $handler = new ExceptionHandler();
        $result = $this->app->use($handler);
        $this->assertTrue(true); // No exception thrown
    }

    public function testUseInvalidObjectThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->app->use(new \stdClass());
    }

    public function testRouterMethodDelegation(): void
    {
        $this->app->GET('/test', function ($req) {
            return 'test';
        });

        $uri = new Uri();
        $uri->setPath('/test');
        $request = TestHelper::createServerRequest('GET', $uri);

        // Route байгаа тул exception гарч болохгүй, response буцаана
        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleWithClosureRoute(): void
    {
        $this->app->GET('/hello', function ($req) {
            echo 'Hello World';
        });

        $request = TestHelper::createServerRequest('GET', '/hello');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleWithControllerRoute(): void
    {
        $this->app->GET('/test', [ApplicationTestController::class, 'index']);

        $request = TestHelper::createServerRequest('GET', '/test');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleWithRouteParameters(): void
    {
        $this->app->GET('/user/{int:id}', function ($req) {
            $params = $req->getAttribute('params');
            echo "User ID: " . $params['id'];
        });

        $request = TestHelper::createServerRequest('GET', '/user/123');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleUnknownRouteThrowsError(): void
    {
        $request = TestHelper::createServerRequest('GET', '/unknown');

        $this->expectException(\Error::class);
        $this->expectExceptionCode(404);
        $this->app->handle($request);
    }

    public function testHandleWithMiddlewareChain(): void
    {
        $executionOrder = [];

        $middleware1 = new class($executionOrder) implements MiddlewareInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'middleware1-before';
                $response = $handler->handle($request);
                $this->order[] = 'middleware1-after';
                return $response;
            }
        };

        $middleware2 = new class($executionOrder) implements MiddlewareInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'middleware2-before';
                $response = $handler->handle($request);
                $this->order[] = 'middleware2-after';
                return $response;
            }
        };

        $this->app->use($middleware1);
        $this->app->use($middleware2);
        $this->app->GET('/test', function ($req) use (&$executionOrder) {
            $executionOrder[] = 'route';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $this->app->handle($request);

        $this->assertEquals([
            'middleware1-before',
            'middleware2-before',
            'route',
            'middleware2-after',
            'middleware1-after'
        ], $executionOrder);
    }

    public function testHandleWithClosureMiddleware(): void
    {
        $executed = false;
        $this->app->use(function ($request, $handler) use (&$executed) {
            $executed = true;
            return $handler->handle($request);
        });

        $this->app->GET('/test', function ($req) {
            echo 'test';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $this->app->handle($request);
        $this->assertTrue($executed);
    }

    public function testHandleWithSubdirectoryPath(): void
    {
        $this->app->GET('/api/users', function ($req) {
            echo 'users';
        });

        $request = TestHelper::createServerRequest('GET', '/subdirectory/api/users', ['SCRIPT_NAME' => '/subdirectory/index.php']);

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleWithEmptyPath(): void
    {
        $this->app->GET('/', function ($req) {
            echo 'home';
        });

        $request = TestHelper::createServerRequest('GET', '');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}

// Test controller for Application tests
class ApplicationTestController extends \codesaur\Http\Application\Controller
{
    public function index()
    {
        echo 'Test Controller';
    }
}
