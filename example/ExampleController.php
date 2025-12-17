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
     *
     * @return void
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
     *
     * @return void
     *
     * @throws \Error Request body-д firstname байхгүй үед
     *
     * @example
     * POST /post-or-put
     * Body: {"firstname": "John", "lastname": "Doe"}
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
     *
     * @param float $number Route parameter-аас ирсэн float тоо
     * @return void
     *
     * @example
     * GET /float/3.14
     */
    public function float(float $number): void
    {
        \var_dump($number);
    }
}
