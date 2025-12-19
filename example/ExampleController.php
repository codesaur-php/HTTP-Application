<?php

namespace codesaur\Http\Application\Example;

use codesaur\Http\Application\Controller;

/**
 * ExampleController Class
 *
 * Багцын Controller base class ашигласан демо controller.
 *
 * Энэ controller нь GET/POST body, query parameters, attributes
 * зэрэг PSR-7 request өгөгдөлд хэрхэн хандахыг харуулдаг жишээ юм.
 *
 * @package codesaur\Http\Application\Example
 * @author Narankhuu
 * @since 1.0.0
 */
class ExampleController extends Controller
{
    /**
     * Index action - GET /
     *
     * Энгийн index page харуулах.
     * Энэ нь application амжилттай ажиллаж байгааг шалгах энгийн тест endpoint юм.
     *
     * @return void
     *
     * @example
     * GET /
     * Output: "It works! [codesaur\Http\Application\Example\ExampleController]"
     */
    public function index(): void
    {
        echo '<br/>It works! [' . self::class . ']<br/><br/>';
    }

    /**
     * Hello action - GET /hello/{firstname}
     *
     * Route parameter болон query parameter ашиглах жишээ.
     *
     * @param string $firstname Route parameter-аас ирсэн нэр
     * @return void
     *
     * @example
     * GET /hello/John?lastname=Doe
     * Output: "Hello John Doe!"
     */
    public function hello(string $firstname): void
    {
        $user = $firstname;
        $params = $this->getQueryParams();
        if (!empty($params['lastname'])) {
            $user .= " {$params['lastname']}";
        }

        echo "<br/>Hello $user!";
    }

    /**
     * Post/Put action - POST|PUT /post-or-put
     *
     * POST/PUT request body-г parse хийж боловсруулах жишээ.
     * Request body нь JSON эсвэл form-urlencoded байж болно.
     * getParsedBody() method нь парс хийгдсэн массив буцаана.
     *
     * @return void
     *
     * @throws \Error Request body-д firstname байхгүй үед
     *
     * @example
     * POST /post-or-put
     * Content-Type: application/json
     * Body: {"firstname": "John", "lastname": "Doe"}
     * Output: "Hello John Doe!"
     *
     * @example
     * PUT /post-or-put
     * Content-Type: application/x-www-form-urlencoded
     * Body: firstname=Jane&lastname=Smith
     * Output: "Hello Jane Smith!"
     */
    public function post_put(): void
    {
        $payload = $this->getParsedBody();
        if (empty($payload['firstname'])) {
            throw new \Error('Invalid request!');
        }

        $user = $payload['firstname'];
        if (!empty($payload['lastname'])) {
            $user .= " {$payload['lastname']}";
        }
        $this->hello($user);
    }

    /**
     * Float action - GET /float/{float:number}
     *
     * Typed route parameter (float) ашиглах жишээ.
     * Router нь URL-аас float утгыг автоматаар parse хийж,
     * Controller action-ийн аргумент болгон дамжуулна.
     *
     * @param float $number Route parameter-аас ирсэн float тоо
     * @return void
     *
     * @example
     * GET /float/3.14
     * Output: float(3.14)
     *
     * @example
     * GET /float/42.5
     * Output: float(42.5)
     */
    public function float(float $number): void
    {
        \var_dump($number);
    }
}
