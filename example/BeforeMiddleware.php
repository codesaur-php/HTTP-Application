<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class BeforeMiddleware
 *
 * RequestHandlerInterface::handle() гүйцэтгэхийн өмнөх middleware.
 * Энэ middleware нь request объектод "start_time" attribute-ийг нэмдэг.
 *
 * → Request lifecycle эхлэх timestamp хадгална.
 */
class BeforeMiddleware implements MiddlewareInterface
{
    /**
     * @inheritdoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $modified_request = $request->withAttribute('start_time', \microtime());

        return $handler->handle($modified_request);
    }
}
