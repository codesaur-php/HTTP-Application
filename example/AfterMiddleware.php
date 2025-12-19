<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AfterMiddleware Class
 *
 * RequestHandlerInterface::handle() дууссаны дараа ажилладаг middleware.
 *
 * Энэ middleware нь:
 * - Response дээр "end_time" header нэмэх
 * - Request processing хугацааг тооцоолж консолд хэвлэх
 * - BeforeMiddleware-ээс ирсэн start_time-ийг ашиглана
 *
 * @package codesaur\Http\Application\Example
 * @author Narankhuu
 * @since 1.0.0
 * @implements MiddlewareInterface
 */
class AfterMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     *
     * Handler-г дуудаж, response дээр end_time header нэмж,
     * processing хугацааг хэвлэнэ.
     *
     * Энэ middleware нь:
     * 1. Handler-г дуудаж response авна
     * 2. Response дээр "end_time" header нэмнэ
     * 3. BeforeMiddleware-ээс ирсэн start_time-ийг ашиглан duration тооцоолж хэвлэнэ
     *
     * @param ServerRequestInterface $request PSR-7 ServerRequest объект
     * @param RequestHandlerInterface $handler Дараагийн handler (route handler)
     * @return ResponseInterface PSR-7 Response объект (end_time header-тэй)
     *
     * @example
     * // Response headers-д end_time нэмэгдэнэ
     * $endTime = $response->getHeaderLine('end_time'); // float timestamp string
     *
     * // HTML output дээр duration хэвлэгдэнэ
     * // "Request started at {1234567890.1234} and finished in {1234567890.5678} (Duration: 0.4444 seconds)"
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $endTime = \microtime(true);
        $response = $handler->handle($request)->withHeader('end_time', (string)$endTime);

        $startTime = $request->getAttribute('start_time');
        if ($startTime !== null) {
            $duration = $endTime - (float)$startTime;
            echo \sprintf(
                '<hr><i style="color:grey">Request started at {%s} and finished in {%s} (Duration: %.4f seconds)</i>',
                $startTime,
                $endTime,
                $duration
            );
        }

        return $response;
    }
}
