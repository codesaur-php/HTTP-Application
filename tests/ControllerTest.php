<?php

namespace codesaur\Http\Application\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\Uri;
use codesaur\Http\Application\Controller;
use codesaur\Http\Application\Tests\TestHelper;

class ControllerTest extends TestCase
{
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        $this->request = (new ServerRequest())
            ->withMethod('GET')
            ->withUri(TestHelper::createUri('/test'));
    }

    public function testControllerConstructor(): void
    {
        $controller = new ControllerTestController($this->request);
        $this->assertInstanceOf(Controller::class, $controller);
    }

    public function testGetRequest(): void
    {
        $controller = new ControllerTestController($this->request);
        $this->assertSame($this->request, $controller->getRequest());
    }

    public function testGetParsedBodyWithNull(): void
    {
        $controller = new ControllerTestController($this->request);
        $body = $controller->getParsedBody();
        $this->assertIsArray($body);
        $this->assertEmpty($body);
    }

    public function testGetParsedBodyWithArray(): void
    {
        $request = $this->request->withParsedBody(['name' => 'John', 'age' => 30]);
        $controller = new ControllerTestController($request);
        $body = $controller->getParsedBody();
        $this->assertEquals(['name' => 'John', 'age' => 30], $body);
    }

    public function testGetQueryParams(): void
    {
        $request = $this->request->withQueryParams(['page' => 1, 'limit' => 10]);
        $controller = new ControllerTestController($request);
        $params = $controller->getQueryParams();
        $this->assertEquals(['page' => 1, 'limit' => 10], $params);
    }

    public function testGetQueryParamsEmpty(): void
    {
        $controller = new ControllerTestController($this->request);
        $params = $controller->getQueryParams();
        $this->assertIsArray($params);
    }

    public function testGetAttributes(): void
    {
        $request = $this->request
            ->withAttribute('params', ['id' => 123])
            ->withAttribute('custom', 'value');
        $controller = new ControllerTestController($request);
        $attributes = $controller->getAttributes();
        $this->assertArrayHasKey('params', $attributes);
        $this->assertArrayHasKey('custom', $attributes);
        $this->assertEquals(['id' => 123], $attributes['params']);
        $this->assertEquals('value', $attributes['custom']);
    }

    public function testGetAttribute(): void
    {
        $request = $this->request->withAttribute('id', 123);
        $controller = new ControllerTestController($request);
        $id = $controller->getAttribute('id');
        $this->assertEquals(123, $id);
    }

    public function testGetAttributeWithDefault(): void
    {
        $controller = new ControllerTestController($this->request);
        $value = $controller->getAttribute('nonexistent', 'default');
        $this->assertEquals('default', $value);
    }

    public function testGetAttributeWithoutDefault(): void
    {
        $controller = new ControllerTestController($this->request);
        $value = $controller->getAttribute('nonexistent');
        $this->assertNull($value);
    }
}

// Test controller implementation for Controller tests
class ControllerTestController extends Controller
{
    // Empty implementation for testing base class methods
}
