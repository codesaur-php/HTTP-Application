<?php

namespace codesaur\Http\Application\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\Uri;
use codesaur\Http\Message\NonBodyResponse;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;
use codesaur\Http\Application\ExceptionHandlerInterface;
use codesaur\Router\Router;
use codesaur\Router\RouterInterface;
use codesaur\Http\Application\Tests\TestHelper;

class ApplicationTest extends TestCase
{
    private Application $app;
    private Router $router;

    protected function setUp(): void
    {
        $this->app = new Application(new NonBodyResponse());
        $this->router = new Router();
        $this->app->use($this->router);
    }

    public function testApplicationImplementsRequestHandlerInterface(): void
    {
        $this->assertInstanceOf(RequestHandlerInterface::class, $this->app);
    }

    public function testEmptyApplicationHasNoRouters(): void
    {
        $bare = new Application(new NonBodyResponse());
        $this->assertCount(0, $bare->getRouters(), 'Empty Application нь Router-гүй');
    }

    public function testUseAddsRouter(): void
    {
        $bare = new Application(new NonBodyResponse());
        $r = new Router();
        $bare->use($r);
        $routers = $bare->getRouters();
        $this->assertCount(1, $routers);
        $this->assertSame($r, $routers[0]);
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
        $this->router->GET('/test', function ($req) {
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
        $this->router->GET('/hello', function ($req) {
            echo 'Hello World';
        });

        $request = TestHelper::createServerRequest('GET', '/hello');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleWithControllerRoute(): void
    {
        $this->router->GET('/test', [ApplicationTestController::class, 'index']);

        $request = TestHelper::createServerRequest('GET', '/test');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleWithRouteParameters(): void
    {
        $this->router->GET('/user/{int:id}', function ($req) {
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
        $this->router->GET('/test', function ($req) use (&$executionOrder) {
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

        $this->router->GET('/test', function ($req) {
            echo 'test';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $this->app->handle($request);
        $this->assertTrue($executed);
    }

    public function testHandleWithSubdirectoryPath(): void
    {
        $this->router->GET('/api/users', function ($req) {
            echo 'users';
        });

        $request = TestHelper::createServerRequest('GET', '/subdirectory/api/users', ['SCRIPT_NAME' => '/subdirectory/index.php']);

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testHandleWithEmptyPath(): void
    {
        $this->router->GET('/', function ($req) {
            echo 'home';
        });

        $request = TestHelper::createServerRequest('GET', '');

        $response = $this->app->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    // ------------------------------------------------------------------
    // Multi-router delegation tests (codesaur/router v6 multi-router API)
    // ------------------------------------------------------------------

    public function testUseAcceptsRouterInterface(): void
    {
        // setUp дотор аль хэдийн нэг router нэмсэн, өөр router нэмж шалгая
        $router = new Router();
        $router->GET('/foo', fn ($req) => null);

        $this->app->use($router);

        $routers = $this->app->getRouters();
        $this->assertCount(2, $routers);
        $this->assertSame($router, $routers[1]);
    }

    public function testMatchDelegatesAcrossRouters(): void
    {
        $r1 = new Router();
        $r1->GET('/from-r1', fn ($req) => null);

        $r2 = new Router();
        $r2->GET('/from-r2', fn ($req) => null);

        $this->app->use($r1);
        $this->app->use($r2);

        $this->assertNotNull($this->app->match('/from-r1', 'GET'));
        $this->assertNotNull($this->app->match('/from-r2', 'GET'));
        $this->assertNull($this->app->match('/nope', 'GET'));
    }

    public function testHandleMatchesAcrossAddedRouters(): void
    {
        $executed = null;
        $r1 = new Router();
        $r1->GET('/api/users', function ($req) use (&$executed) {
            $executed = 'r1';
        });

        $this->app->use($r1);

        $response = $this->app->handle(TestHelper::createServerRequest('GET', '/api/users'));
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('r1', $executed);
    }

    public function testMatchOrderIsFirstAddedWins(): void
    {
        // Эхэлж нэмэгдсэн router-д бүртгэгдсэн route нь дараа нэмэгдсэн router-ийг
        // ялна (registration order - first-added-wins, предиктабл default)
        $this->router->GET('/conflict', function ($req) { echo 'first'; });

        $r2 = new Router();
        $r2->GET('/conflict', function ($req) { echo 'second'; });
        $this->app->use($r2);

        \ob_start();
        $this->app->handle(TestHelper::createServerRequest('GET', '/conflict'));
        $out = \ob_get_clean();

        $this->assertSame('first', $out, 'Эхэлж нэмэгдсэн router ялалт');
    }

    public function testOverrideRouterWinsOverNormalRouters(): void
    {
        // override()-ээр нэмсэн router нь use()-ийн ердийн router-ийг ялна,
        // бүртгэх дараалал хамаагүй (explicit override lane).
        $this->router->GET('/conflict', function ($req) { echo 'core'; });

        $themed = new Router();
        $themed->GET('/conflict', function ($req) { echo 'override'; });
        $this->app->override($themed);

        \ob_start();
        $this->app->handle(TestHelper::createServerRequest('GET', '/conflict'));
        $out = \ob_get_clean();

        $this->assertSame('override', $out, 'Override lane ердийн router-ийг ялах ёстой');
    }

    public function testOverrideReturnsStaticForChaining(): void
    {
        $r = new Router();
        $r->GET('/x', fn ($req) => null);
        $this->assertSame($this->app, $this->app->override($r), 'override() нь $this буцаах ёстой (fluent)');
    }

    public function testGetOverridesReturnsOverrideLane(): void
    {
        $this->assertSame([], $this->app->getOverrides());

        $r = new Router();
        $r->GET('/x', fn ($req) => null);
        $this->app->override($r);

        $this->assertSame([$r], $this->app->getOverrides());
        // override() нь ердийн getRouters() lane-д нэмэгдэхгүй
        $this->assertNotContains($r, $this->app->getRouters());
    }

    public function testGenerateDelegatesAcrossRouters(): void
    {
        $r1 = new Router();
        $r1->GET('/news/{int:id}', fn ($req) => null)->name('news.view');

        $r2 = new Router();
        $r2->GET('/users/{int:id}', fn ($req) => null)->name('user.view');

        $this->app->use($r1);
        $this->app->use($r2);

        $this->assertSame('/news/42', $this->app->generate('news.view', ['id' => 42]));
        $this->assertSame('/users/7', $this->app->generate('user.view', ['id' => 7]));
    }

    public function testGenerateFirstFoundWinsOnNameCollision(): void
    {
        // Хоёр router ижил name-тэй - эхэлж нэмэгдсэн (use() дарааллаар) ялна
        $r1 = new Router();
        $r1->GET('/r1/{int:id}', fn ($req) => null)->name('shared');

        $r2 = new Router();
        $r2->GET('/r2/{int:id}', fn ($req) => null)->name('shared');

        $this->app->use($r1);
        $this->app->use($r2);

        $this->assertSame('/r1/9', $this->app->generate('shared', ['id' => 9]));
    }

    public function testGenerateOverrideLaneWinsOnNameCollision(): void
    {
        // override lane-д ижил name байвал ердийн router-ийг ялна
        $r1 = new Router();
        $r1->GET('/r1/{int:id}', fn ($req) => null)->name('shared');
        $this->app->use($r1);

        $themed = new Router();
        $themed->GET('/themed/{int:id}', fn ($req) => null)->name('shared');
        $this->app->override($themed);

        $this->assertSame('/themed/9', $this->app->generate('shared', ['id' => 9]));
    }

    public function testGenerateThrowsWhenNameNotFoundInAnyRouter(): void
    {
        $r1 = new Router();
        $r1->GET('/foo', fn ($req) => null)->name('foo');
        $this->app->use($r1);

        $this->expectException(\OutOfRangeException::class);
        $this->app->generate('does-not-exist');
    }

    public function testPatternDelegatesAcrossRouters(): void
    {
        $r1 = new Router();
        $r1->GET('/news/{int:id}/{slug}', fn ($req) => null)->name('news.view');
        $this->app->use($r1);

        $this->assertSame('/news/{id}/{slug}', $this->app->pattern('news.view'));
    }

    public function testGetRoutesAggregatesAcrossRouters(): void
    {
        $r1 = new Router();
        $r1->GET('/a', fn ($req) => null);

        $r2 = new Router();
        $r2->POST('/b', fn ($req) => null);

        $this->app->use($r1);
        $this->app->use($r2);

        $routes = $this->app->getRoutes();
        $this->assertArrayHasKey('/a', $routes);
        $this->assertArrayHasKey('/b', $routes);
        $this->assertArrayHasKey('GET', $routes['/a']);
        $this->assertArrayHasKey('POST', $routes['/b']);
    }

    public function testGetRoutesFirstFoundWinsOnPatternMethodCollision(): void
    {
        $this->router->GET('/x', function ($req) { return 'first'; });

        $r2 = new Router();
        $r2->GET('/x', function ($req) { return 'second'; });
        $this->app->use($r2);

        $routes = $this->app->getRoutes();
        // Эхэлж бүртгэсэн callable хадгалагдсан байх ёстой
        $this->assertSame('first', ($routes['/x']['GET'][0])(null));
    }

    public function testGetRoutesOverrideLaneWinsOnPatternMethodCollision(): void
    {
        $this->router->GET('/x', function ($req) { return 'core'; });

        $themed = new Router();
        $themed->GET('/x', function ($req) { return 'override'; });
        $this->app->override($themed);

        $routes = $this->app->getRoutes();
        // Override lane-ийн callable хадгалагдсан байх ёстой (match()-тэй тогтворжсон)
        $this->assertSame('override', ($routes['/x']['GET'][0])(null));
    }

    public function testApplicationIsStoredInApplicationRequestAttribute(): void
    {
        // 'application' attribute нь Application instance өөрөө болно.
        // Энэ нь Controller-аас mount-aware generate()/pattern() дуудах боломж олгоно.
        $capturedApp = null;
        $r2 = new Router();
        $r2->GET('/probe', function ($req) use (&$capturedApp) {
            $capturedApp = $req->getAttribute('application');
        });
        $this->app->use($r2);

        $this->app->handle(TestHelper::createServerRequest('GET', '/probe'));

        $this->assertSame(
            $this->app,
            $capturedApp,
            "'application' attribute нь Application instance өөрөө байх ёстой (mount-aware delegation-д)"
        );
    }

    // ------------------------------------------------------------------
    // Mount feature tests
    // ------------------------------------------------------------------

    public function testMountSetsPathAndReturnsStaticForChaining(): void
    {
        $result = $this->app->mount('/dashboard');
        $this->assertSame($this->app, $result, 'mount() нь $this буцаах ёстой (fluent)');
        $this->assertSame('/dashboard', $this->app->getMountPath());
    }

    public function testMountNormalizesSlashes(): void
    {
        $this->assertSame('/dashboard', (new Application(new NonBodyResponse()))->mount('/dashboard')->getMountPath());
        $this->assertSame('/dashboard', (new Application(new NonBodyResponse()))->mount('dashboard')->getMountPath());
        $this->assertSame('/dashboard', (new Application(new NonBodyResponse()))->mount('/dashboard/')->getMountPath());
        $this->assertSame('/dashboard', (new Application(new NonBodyResponse()))->mount('dashboard/')->getMountPath());
    }

    public function testMountEmptyOrSlashMeansNoMount(): void
    {
        $this->assertSame('', (new Application(new NonBodyResponse()))->mount('')->getMountPath());
        $this->assertSame('', (new Application(new NonBodyResponse()))->mount('/')->getMountPath());
    }

    public function testMountedMatchStripsPrefixBeforeRouterLookup(): void
    {
        $this->app->mount('/dashboard');
        $this->router->GET('/users', fn ($req) => null);

        $result = $this->app->match('/dashboard/users', 'GET');
        $this->assertNotNull($result, 'Mount prefix-той path нь Router-д таарах ёстой');
    }

    public function testMountedMatchHandlesExactMountPath(): void
    {
        $this->app->mount('/dashboard');
        $this->router->GET('/', fn ($req) => null);

        // /dashboard (trailing slash-гүй) -> Router-д '/' гэж очно
        $result = $this->app->match('/dashboard', 'GET');
        $this->assertNotNull($result);
    }

    public function testMountedMatchReturnsNullForOutOfPrefixPath(): void
    {
        $this->app->mount('/dashboard');
        $this->router->GET('/users', fn ($req) => null);

        $this->assertNull($this->app->match('/api/users', 'GET'));
        $this->assertNull($this->app->match('/users', 'GET'), 'Mount prefix-гүй path таарах ёсгүй');
    }

    public function testMountBoundaryProtectionPreventsPartialMatch(): void
    {
        // /dashboard prefix нь /dashboardx-д таарах ёсгүй (substring биш boundary)
        $this->app->mount('/dashboard');
        $this->router->GET('/users', fn ($req) => null);

        $this->assertNull(
            $this->app->match('/dashboardx/users', 'GET'),
            '/dashboardx нь /dashboard-ийн доорх биш - таарах ёсгүй'
        );
    }

    public function testMountedGeneratePrependsPrefix(): void
    {
        $this->app->mount('/dashboard');
        $this->router->GET('/users/{int:id}', fn ($req) => null)->name('user-view');

        $this->assertSame('/dashboard/users/42', $this->app->generate('user-view', ['id' => 42]));
    }

    public function testMountedPatternPrependsPrefix(): void
    {
        $this->app->mount('/dashboard');
        $this->router->GET('/users/{int:id}', fn ($req) => null)->name('user-view');

        $this->assertSame('/dashboard/users/{id}', $this->app->pattern('user-view'));
    }

    public function testMountedGetRoutesReturnsPrefixedPatterns(): void
    {
        $this->app->mount('/dashboard');
        $this->router->GET('/users', fn ($req) => null);
        $this->router->POST('/posts', fn ($req) => null);

        $routes = $this->app->getRoutes();
        $this->assertArrayHasKey('/dashboard/users', $routes);
        $this->assertArrayHasKey('/dashboard/posts', $routes);
        $this->assertArrayNotHasKey('/users', $routes, 'Prefix-гүй pattern байх ёсгүй');
    }

    public function testMountedHandleEndToEnd(): void
    {
        $executed = null;
        $this->app->mount('/dashboard');
        $this->router->GET('/users', function ($req) use (&$executed) {
            $executed = 'users-handler';
        });

        $response = $this->app->handle(TestHelper::createServerRequest('GET', '/dashboard/users'));
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('users-handler', $executed);
    }

    public function testMountedHandleThrows404ForOutOfPrefixPath(): void
    {
        $this->app->mount('/dashboard');
        $this->router->GET('/users', fn ($req) => null);

        $this->expectException(\Error::class);
        $this->expectExceptionCode(404);
        $this->app->handle(TestHelper::createServerRequest('GET', '/api/users'));
    }

    public function testMountWithMultipleRoutersPrefixesAll(): void
    {
        $r1 = new Router();
        $r1->GET('/users', fn ($req) => null)->name('users');

        $r2 = new Router();
        $r2->GET('/posts', fn ($req) => null)->name('posts');

        $this->app->mount('/admin');
        $this->app->use($r1);
        $this->app->use($r2);

        $this->assertSame('/admin/users', $this->app->generate('users'));
        $this->assertSame('/admin/posts', $this->app->generate('posts'));
        $this->assertNotNull($this->app->match('/admin/users', 'GET'));
        $this->assertNotNull($this->app->match('/admin/posts', 'GET'));
        $this->assertNull($this->app->match('/users', 'GET'));
    }

    // ------------------------------------------------------------------
    // Per-route middleware validation tests
    // ------------------------------------------------------------------

    public function testPerRouteMiddlewareAcceptsMiddlewareInterface(): void
    {
        $executed = false;
        $mw = new class($executed) implements MiddlewareInterface {
            private bool $executed;
            public function __construct(bool &$executed) { $this->executed = &$executed; }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->executed = true;
                return $handler->handle($request);
            }
        };

        $this->router->GET('/x', fn ($req) => null)->middleware([$mw]);

        $this->app->handle(TestHelper::createServerRequest('GET', '/x'));
        $this->assertTrue($executed);
    }

    public function testPerRouteMiddlewareAcceptsClosure(): void
    {
        $executed = false;
        $this->router->GET('/x', fn ($req) => null)->middleware([
            function (ServerRequestInterface $req, RequestHandlerInterface $handler) use (&$executed) {
                $executed = true;
                return $handler->handle($req);
            },
        ]);

        $this->app->handle(TestHelper::createServerRequest('GET', '/x'));
        $this->assertTrue($executed);
    }

    public function testPerRouteMiddlewareAcceptsClassStringWithLazyInstantiation(): void
    {
        $this->router->GET('/x', fn ($req) => null)->middleware([
            PassThroughTestMiddleware::class,
        ]);

        $response = $this->app->handle(TestHelper::createServerRequest('GET', '/x'));
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testPerRouteMiddlewareThrowsForNonExistentClassString(): void
    {
        $this->router->GET('/x', fn ($req) => null)->middleware([
            'NonExistent\\MiddlewareClass',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $this->app->handle(TestHelper::createServerRequest('GET', '/x'));
    }

    public function testPerRouteMiddlewareThrowsForInvalidType(): void
    {
        // \stdClass нь MiddlewareInterface ч биш, Closure ч биш
        $this->router->GET('/x', fn ($req) => null)->middleware([
            new \stdClass(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be MiddlewareInterface or Closure/');
        $this->app->handle(TestHelper::createServerRequest('GET', '/x'));
    }

    public function testPerRouteMiddlewareThrowsWhenClassStringNotMiddleware(): void
    {
        // Class байгаа боловч MiddlewareInterface implement хийгээгүй
        $this->router->GET('/x', fn ($req) => null)->middleware([
            \stdClass::class,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be MiddlewareInterface or Closure/');
        $this->app->handle(TestHelper::createServerRequest('GET', '/x'));
    }

    public function testMountedApplicationAttributeProvidesAutoPrefixGenerate(): void
    {
        // Critical use case: Controller `$req->getAttribute('application')->generate('name')`
        // нь mount prefix-ийг автоматаар нэмэх ёстой - PrivateFilesController-ийн
        // problemийг шийдэх загвар.
        $generatedUrl = null;
        $this->app->mount('/dashboard');
        $this->router->GET('/private/file', function ($req) use (&$generatedUrl) {
            $generatedUrl = $req->getAttribute('application')->generate('private-file');
        })->name('private-file');

        $this->app->handle(TestHelper::createServerRequest('GET', '/dashboard/private/file'));

        $this->assertSame(
            '/dashboard/private/file',
            $generatedUrl,
            'Controller-ээс generate() дуудахад mount prefix автоматаар нэмэгдэх ёстой'
        );
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

// Test middleware for per-route middleware class-string instantiation test
class PassThroughTestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
