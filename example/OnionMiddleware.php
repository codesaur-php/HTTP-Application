<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class OnionMiddleware
 *
 * "Сонгино middleware" буюу before/after хэлбэрийн давхарласан middleware жишээ.
 *
 * Handler-г дуудсаны өмнө болон дараа console dump хийнэ.
 */
class OnionMiddleware implements MiddlewareInterface
{
    /**
     * @inheritdoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        \var_dump("i'm onion before");

        $response = $handler->handle($request);

        \var_dump("i'm onion after");

        return $response;
    }
}
