<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * OnionMiddleware Class
 *
 * "Сонгино middleware" буюу before/after хэлбэрийн давхарласан middleware жишээ.
 *
 * Энэ middleware нь PSR-15 стандартын onion model-ийг харуулдаг:
 * - Handler-г дуудсаны өмнө "before" логик ажиллуулна
 * - Handler-г дуудаж response авна
 * - Handler-г дуудсаны дараа "after" логик ажиллуулна
 *
 * @package codesaur\Http\Application\Example
 * @author Narankhuu
 * @since 1.0.0
 * @implements MiddlewareInterface
 */
class OnionMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     *
     * Handler-г дуудсаны өмнө болон дараа console dump хийнэ.
     * Энэ нь middleware chain-ийн ажиллах дарааллыг харуулдаг.
     *
     * PSR-15 стандартын onion model-ийг харуулдаг:
     * 1. "Before" логик: Handler-г дуудахаас өмнө ажиллана
     * 2. Handler дуудах: Дараагийн middleware эсвэл route handler-г дуудна
     * 3. "After" логик: Handler-г дуудсаны дараа ажиллана
     *
     * Энэ нь middleware-үүдийн execution order-ийг ойлгоход тустай.
     *
     * @param ServerRequestInterface $request PSR-7 ServerRequest объект
     * @param RequestHandlerInterface $handler Дараагийн handler (middleware эсвэл route handler)
     * @return ResponseInterface PSR-7 Response объект
     *
     * @example
     * // Output:
     * // string(18) "i'm onion before"
     * // ... (handler execution) ...
     * // string(17) "i'm onion after"
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        \var_dump("i'm onion before");

        $response = $handler->handle($request);

        \var_dump("i'm onion after");

        return $response;
    }
}
