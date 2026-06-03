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
     * "It works!" мессеж гаргаад доор нь бусад жишээ route-уудын clickable
     * жагсаалтыг үзүүлнэ - developer шууд дарж туршиж болно.
     *
     * @return void
     *
     * @example
     * GET /
     * Output: "It works! [...]" + жишээ route-уудын линк жагсаалт
     */
    public function index(): void
    {
        echo '<br/>It works! [' . self::class . ']<br/><br/>';

        // Subdirectory дээр ажиллахад линк зөв болгохын тулд script base path
        // тооцоолно. Энэ нь Application::handle()-ийн зүсдэг dirname(SCRIPT_NAME)-ийн
        // урвуу тул линкүүд routing-тай яг тааруулагдана.
        $base = \dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
        if ($base === '/' || $base === '\\' || $base === '.') {
            $base = '';
        }

        // GET-ээр шууд дарж туршиж болох route-ууд (жишээ утга суулгасан)
        $clickable = [
            '/home'           => 'home - Closure route',
            '/hello/World'    => 'hi - GET /hello/{firstname}',
            '/hello/John/Doe' => 'hello - GET /hello/{firstname}/{lastname}',
            '/echo/codesaur'  => 'echo - GET|POST|PUT|DELETE|OPTIONS route',
            '/float/3.14'     => 'float - {float:number} типтэй параметр',
            '/sum/2/3'        => 'sum - {int:a}/{uint:b} типтэй параметр',
            '/guarded'        => 'guarded - per-route middleware-тэй',
        ];

        echo '<strong>Бусад жишээ route (дарж туршина уу):</strong><ul>';
        foreach ($clickable as $path => $label) {
            $href = $base . $path;
            echo '<li><a href="' . $href . '">' . $href . '</a> - ' . $label . '</li>';
        }
        echo '</ul>';

        // POST/PUT-ээр л дуудагддаг тул энгийн линкээр дарж болохгүй (body шаардана)
        echo '<strong>POST/PUT (линкээр биш, request body шаардана):</strong><ul>';
        echo '<li>POST|PUT <code>' . $base . '/post-or-put</code> - body: firstname, lastname</li>';
        echo '<li>POST <code>' . $base . '/hello/post</code> - body: firstname, lastname</li>';
        echo '</ul>';
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
