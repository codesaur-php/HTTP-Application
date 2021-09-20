<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BeforeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $modified_request = $request->withAttribute('start_time', microtime());
        
        return $handler->handle($modified_request);
    }
}
