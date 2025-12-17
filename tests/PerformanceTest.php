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
use codesaur\Http\Application\Tests\TestHelper;

/**
 * Performance Tests
 *
 * Application-ийн performance тестүүд.
 * Олон middleware, олон route, олон request зэрэг
 * performance-д нөлөөлөх тохиолдлуудыг тестлэх.
 *
 * @package codesaur\Http\Application\Tests
 */
class PerformanceTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
    }

    public function testManyMiddlewarePerformance(): void
    {
        // 10 middleware нэмэх
        for ($i = 0; $i < 10; $i++) {
            $this->app->use(new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            });
        }

        $this->app->GET('/test', function ($req) {
            echo 'test';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $start = microtime(true);
        $response = $this->app->handle($request);
        $end = microtime(true);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        
        // Performance assertion: 10 middleware-т 0.1 секундэд дуусна
        $this->assertLessThan(0.1, $end - $start, '10 middleware should complete in less than 0.1 seconds');
    }

    public function testManyRoutesPerformance(): void
    {
        // 100 route нэмэх
        for ($i = 0; $i < 100; $i++) {
            $this->app->GET("/route$i", function ($req) {
                echo "route$i";
            });
        }

        // Сүүлийн route-г тестлэх
        $request = TestHelper::createServerRequest('GET', '/route99');

        $start = microtime(true);
        $response = $this->app->handle($request);
        $end = microtime(true);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        
        // Performance assertion: 100 route-т match хийхэд 0.05 секундэд дуусна
        $this->assertLessThan(0.05, $end - $start, 'Route matching with 100 routes should be fast');
    }

    public function testRepeatedRequestsPerformance(): void
    {
        $this->app->GET('/test', function ($req) {
            echo 'test';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $iterations = 100;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $response = $this->app->handle($request);
        }

        $end = microtime(true);
        $totalTime = $end - $start;
        $avgTime = $totalTime / $iterations;

        // Performance assertion: Нэг request дунджаар 0.001 секундэд дуусна
        $this->assertLessThan(0.001, $avgTime, "Average request time should be less than 1ms");
    }

    public function testDeepMiddlewareStackPerformance(): void
    {
        // 20 middleware (гүн chain)
        for ($i = 0; $i < 20; $i++) {
            $this->app->use(new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }
            });
        }

        $this->app->GET('/test', function ($req) {
            echo 'test';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $start = microtime(true);
        $response = $this->app->handle($request);
        $end = microtime(true);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        
        // Performance assertion: 20 middleware-т 0.2 секундэд дуусна
        $this->assertLessThan(0.2, $end - $start, '20 middleware should complete in reasonable time');
    }

    public function testComplexRouteMatchingPerformance(): void
    {
        // Олон төрлийн route pattern
        $patterns = [
            '/user/{int:id}',
            '/user/{int:id}/post/{int:postId}',
            '/user/{int:id}/post/{int:postId}/comment/{int:commentId}',
            '/api/v1/users/{int:id}',
            '/api/v2/users/{int:id}/posts',
        ];

        foreach ($patterns as $pattern) {
            $this->app->GET($pattern, function ($req) {
                echo 'matched';
            });
        }

        // Сүүлийн pattern-г тестлэх
        $request = TestHelper::createServerRequest('GET', '/api/v2/users/123/posts');

        $start = microtime(true);
        $response = $this->app->handle($request);
        $end = microtime(true);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertLessThan(0.05, $end - $start, 'Complex route matching should be fast');
    }

    public function testMemoryUsageWithManyRoutes(): void
    {
        $initialMemory = memory_get_usage();

        // 1000 route нэмэх
        for ($i = 0; $i < 1000; $i++) {
            $this->app->GET("/route$i/{int:param}", function ($req) {
                echo 'test';
            });
        }

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        // Memory assertion: 1000 route-д 10MB-аас бага санах ой ашиглана
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, '1000 routes should use less than 10MB memory');
    }

    public function testConcurrentRequestSimulation(): void
    {
        $this->app->GET('/test', function ($req) {
            usleep(1000); // 1ms delay
            echo 'test';
        });

        $request = TestHelper::createServerRequest('GET', '/test');

        $iterations = 50;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $response = $this->app->handle($request);
        }

        $end = microtime(true);
        $totalTime = $end - $start;

        // Performance assertion: 50 request 1 секундэд дуусна
        $this->assertLessThan(1.0, $totalTime, '50 requests should complete in less than 1 second');
    }
}
