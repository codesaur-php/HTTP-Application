<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AfterMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request)->withHeader('end_time', microtime());
        
        echo sprintf('<hr><i style="color:grey">Request started at {%s} and finished in {%s}</i>', $request->getAttribute('start_time'), current($response->getHeader('end_time')));
        
        return $response;
    }
}
