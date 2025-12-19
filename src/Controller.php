<?php

namespace codesaur\Http\Application;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller Base Class
 *
 * Бүх Controller классуудын суурь анги.
 *
 * Энэ класс нь PSR-7 ServerRequest объектын shortcut getter method-үүдийг агуулна.
 * Controller-үүд энэ класс-аас удамшиж, request мэдээлэлд хялбар хандах боломжтой.
 *
 * @package codesaur\Http\Application
 * @author Narankhuu
 * @since 1.0.0
 */
abstract class Controller
{
    /**
     * Ирсэн HTTP хүсэлт (PSR-7 ServerRequest).
     *
     * @var ServerRequestInterface
     */
    protected ServerRequestInterface $request;

    /**
     * Controller конструктор.
     *
     * Controller үүсэхэд PSR-7 ServerRequest автоматаар дамжина.
     * Энэ request-г бүх method-үүдэд ашиглаж болно.
     *
     * @param ServerRequestInterface $request PSR-7 ServerRequest объект
     */
    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Request объектыг авах.
     *
     * Controller-ийн бүх method-үүдэд PSR-7 ServerRequest объектод хандах боломж олгоно.
     *
     * @return ServerRequestInterface PSR-7 ServerRequest объект
     *
     * @example
     * $request = $this->getRequest();
     * $method = $request->getMethod(); // GET, POST, PUT, DELETE, etc.
     * $uri = $request->getUri()->getPath(); // /user/123
     */
    public final function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * POST/PUT/JSON parsed body-г буцаах.
     *
     * Request body нь JSON эсвэл form-urlencoded байвал
     * парс хийгдсэн массив буцаана. Null бол хоосон массив буцаана.
     *
     * @return array<string, mixed> Parsed body массив
     *
     * @example
     * $data = $this->getParsedBody();
     * $name = $data['name'] ?? 'Unknown';
     */
    public final function getParsedBody(): array
    {
        $parsedBody = $this->request->getParsedBody();
        return $parsedBody === null ? [] : (array)$parsedBody;
    }

    /**
     * Query string параметрүүдийг авах.
     *
     * URL-ийн query string-ээс параметрүүдийг авна.
     * Жишээ: ?page=1&limit=10 → ['page' => '1', 'limit' => '10']
     *
     * @return array<string, mixed> Query параметрүүдийн массив
     *
     * @example
     * $params = $this->getQueryParams();
     * $page = $params['page'] ?? 1;
     */
    public final function getQueryParams(): array
    {
        return $this->request->getQueryParams();
    }

    /**
     * Бүх request attributes-г авах.
     *
     * Attributes нь route parameters, router instance,
     * middleware-ээс нэмсэн custom attributes зэрэг байж болно.
     *
     * @return array<string, mixed> Бүх attributes-ийн массив
     *
     * @example
     * $attrs = $this->getAttributes();
     * $params = $attrs['params'] ?? [];
     * $router = $attrs['router'] ?? null;
     */
    public final function getAttributes(): array
    {
        return $this->request->getAttributes();
    }

    /**
     * Нэг attribute-г авах.
     *
     * Request attributes нь route parameters, router instance,
     * middleware-ээс нэмсэн custom attributes зэрэг байж болно.
     *
     * @param string $name Attribute-ийн нэр
     * @param mixed $default Attribute байхгүй бол буцаах default утга
     * @return mixed Attribute-ийн утга эсвэл default утга
     *
     * @example
     * // Route parameters авах
     * $params = $this->getAttribute('params');
     * $userId = $params['id'] ?? null;
     *
     * // Router instance авах
     * $router = $this->getAttribute('router');
     *
     * // Middleware-ээс нэмсэн custom attribute
     * $startTime = $this->getAttribute('start_time', 0);
     */
    public final function getAttribute(string $name, $default = null)
    {
        return $this->request->getAttribute($name, $default);
    }
}
