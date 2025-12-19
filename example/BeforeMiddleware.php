<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * BeforeMiddleware Class
 *
 * RequestHandlerInterface::handle() гүйцэтгэхийн өмнөх middleware.
 *
 * Энэ middleware нь request объектод "start_time" attribute-ийг нэмдэг.
 * Энэ нь request lifecycle эхлэх timestamp-ийг хадгалж,
 * дараагийн middleware эсвэл after middleware-д ашиглах боломжтой.
 *
 * @package codesaur\Http\Application\Example
 * @author Narankhuu
 * @since 1.0.0
 * @implements MiddlewareInterface
 */
class BeforeMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     *
     * Request-д start_time attribute нэмж, дараагийн handler-д дамжуулна.
     *
     * Энэ middleware нь request processing эхлэх timestamp-ийг хадгалж,
     * дараагийн middleware эсвэл after middleware-д ашиглах боломжтой болгоно.
     * Жишээ: AfterMiddleware нь start_time-ийг ашиглан request duration тооцоолно.
     *
     * @param ServerRequestInterface $request PSR-7 ServerRequest объект
     * @param RequestHandlerInterface $handler Дараагийн handler (middleware эсвэл route handler)
     * @return ResponseInterface PSR-7 Response объект
     *
     * @example
     * // Request attributes-д start_time нэмэгдэнэ
     * $startTime = $request->getAttribute('start_time'); // float timestamp
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $modified_request = $request->withAttribute('start_time', \microtime(true));

        return $handler->handle($modified_request);
    }
}
