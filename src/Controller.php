<?php

namespace codesaur\Http\Application;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Controller
 *
 * Бүх Controller классуудын суурь анги.
 * Request объектын shortcut getter-үүдийг агуулна.
 *
 * @package codesaur\Http\Application
 */
abstract class Controller
{
    /** @var ServerRequestInterface Ирсэн HTTP хүсэлт */
    protected ServerRequestInterface $request;

    /**
     * Controller үүсэхэд Request автоматаар дамжина.
     *
     * @param ServerRequestInterface $request
     */
    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Request-г авах.
     *
     * @return ServerRequestInterface
     */
    public final function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * POST/PUT/JSON parsed body буцаах.
     * Null бол хоосон массив буцаана.
     *
     * @return array
     */
    public final function getParsedBody(): array
    {
        $parsedBody = $this->request->getParsedBody();
        return $parsedBody === null ? [] : (array)$parsedBody;
    }

    /**
     * Query string параметрүүд авах (?page=1 гэх мэт)
     *
     * @return array
     */
    public final function getQueryParams(): array
    {
        return $this->request->getQueryParams();
    }

    /**
     * Бүх attributes-г авах (route params, router, custom attributes)
     *
     * @return array
     */
    public final function getAttributes(): array
    {
        return $this->request->getAttributes();
    }

    /**
     * Нэг attribute-г авах.
     *
     * @param string $name
     * @param mixed $default Attribute байхгүй бол буцаах default утга
     * @return mixed
     */
    public final function getAttribute(string $name, $default = null)
    {
        return $this->request->getAttribute($name, $default);
    }
}
