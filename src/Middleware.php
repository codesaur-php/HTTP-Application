<?php declare(strict_types=1);

namespace codesaur\Http\Application;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Middleware implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new \RuntimeException(__CLASS__ . ':' . __FUNCTION__ . ' Not implemented');
    }
}
