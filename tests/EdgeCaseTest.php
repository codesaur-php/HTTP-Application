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
use codesaur\Http\Application\Controller;
use codesaur\Http\Application\Tests\TestHelper;

/**
 * Edge Case Tests
 *
 * Application-ийн edge case болон boundary condition тестүүд.
 *
 * @package codesaur\Http\Application\Tests
 */
class EdgeCaseTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
    }

    public function testEmptyPath(): void
    {
        $this->app->GET('/', function ($req) {
            echo 'root';
        });

        $request = TestHelper::createServerRequest('GET', '');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testVeryLongPath(): void
    {
        $longPath = '/' . str_repeat('a', 1000);
        $this->app->GET($longPath, function ($req) {
            echo 'long';
        });

        $request = TestHelper::createServerRequest('GET', $longPath);

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSpecialCharactersInPath(): void
    {
        // Router нь string type дэмжихгүй, type-гүй параметр ашиглах
        $this->app->GET('/test/{param}', function ($req) {
            $params = $req->getAttribute('params');
            echo $params['param'];
        });

        $specialPath = '/test/' . rawurlencode('test@example.com');
        $request = TestHelper::createServerRequest('GET', $specialPath);

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMultipleRouteParameters(): void
    {
        $this->app->GET('/a/{int:x}/b/{int:y}/c/{int:z}', function ($req) {
            $params = $req->getAttribute('params');
            echo $params['x'] + $params['y'] + $params['z'];
        });

        $request = TestHelper::createServerRequest('GET', '/a/1/b/2/c/3');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMiddlewareThatModifiesRequest(): void
    {
        $this->app->use(new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $request = $request
                    ->withAttribute('modified', true)
                    ->withAttribute('count', 1);
                return $handler->handle($request);
            }
        });

        $this->app->GET('/test', function ($req) {
            $modified = $req->getAttribute('modified');
            $count = $req->getAttribute('count');
            echo "Modified: $modified, Count: $count";
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testMiddlewareThatReturnsEarly(): void
    {
        $executed = false;

        $this->app->use(new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                // Early return without calling handler
                return new \codesaur\Http\Message\NonBodyResponse();
            }
        });

        $this->app->GET('/test', function ($req) use (&$executed) {
            $executed = true;
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertFalse($executed);
    }

    public function testControllerWithNoParameters(): void
    {
        $this->app->GET('/simple', [EdgeCaseTestController::class, 'simple']);

        $request = TestHelper::createServerRequest('GET', '/simple');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testControllerWithManyParameters(): void
    {
        $this->app->GET('/complex/{int:a}/{int:b}/{int:c}/{int:d}/{int:e}', 
            [EdgeCaseTestController::class, 'complex']);

        $request = TestHelper::createServerRequest('GET', '/complex/1/2/3/4/5');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSubdirectoryPathHandling(): void
    {
        $this->app->GET('/api/users', function ($req) {
            echo 'users';
        });

        $request = TestHelper::createServerRequest('GET', '/subdir/api/users', ['SCRIPT_NAME' => '/subdir/index.php']);
        

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testEmptyMiddlewareStack(): void
    {
        $this->app->GET('/test', function ($req) {
            echo 'test';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }
}

// Edge case controller
class EdgeCaseTestController extends Controller
{
    public function simple(): void
    {
        echo 'simple';
    }

    public function complex(int $a, int $b, int $c, int $d, int $e): void
    {
        echo "Sum: " . ($a + $b + $c + $d + $e);
    }
}
