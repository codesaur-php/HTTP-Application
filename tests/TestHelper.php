<?php

namespace codesaur\Http\Application\Tests;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Message\Uri;
use ReflectionClass;

/**
 * Test Helper Functions
 *
 * Тестүүдэд ашиглах helper функцүүд.
 */
class TestHelper
{
    /**
     * Path-тэй Uri үүсгэх helper function.
     *
     * @param string $path URI path
     * @return Uri Uri instance
     */
    public static function createUri(string $path): Uri
    {
        $uri = new Uri();
        $uri->setPath($path);
        return $uri;
    }

    /**
     * Server params-тэй ServerRequest үүсгэх helper function.
     *
     * @param string $method HTTP method
     * @param string|Uri $path URI path эсвэл Uri instance
     * @param array<string, mixed> $serverParams Server parameters
     * @return ServerRequest ServerRequest instance
     */
    public static function createServerRequest(
        string $method = 'GET',
        string|Uri $path = '/',
        array $serverParams = ['SCRIPT_NAME' => '/index.php']
    ): ServerRequest {
        $request = new ServerRequest();
        
        // Method тохируулах
        $request = $request->withMethod($method);
        
        // URI тохируулах
        if (is_string($path)) {
            $uri = self::createUri($path);
        } else {
            $uri = $path;
        }
        $request = $request->withUri($uri);
        
        // Server params-г reflection ашиглан set хийх
        $reflection = new ReflectionClass($request);
        $property = $reflection->getProperty('serverParams');
        $property->setAccessible(true);
        $property->setValue($request, $serverParams);
        
        return $request;
    }
}

