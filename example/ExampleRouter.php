<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use codesaur\Router\Router;

/**
 * ExampleRouter Class
 *
 * codesaur/router-ийн Router class-ийг extend хийсэн модулийн router жишээ.
 *
 * Энэ нь Application-руу use() ашиглан холбогддог:
 *   $app = new Application(new NonBodyResponse());
 *   $app->use(new ExampleRouter());
 *
 * Router нь mount prefix-ийг мэдэхгүй - тиймээс route-уудаа цэвэр
 * path-аар (mount prefix-ГҮЙ) бүртгэнэ. Application::mount() ашигласан
 * үед prefix автоматаар нэмэгдэнэ.
 *
 * Энэ router нь дараах боломжуудыг харуулдаг:
 * - Dynamic parameters (route parameters)
 * - Typed parameters (int, uint, float, string)
 * - Multi-method routes (POST_PUT, GET_POST_PUT_DELETE_OPTIONS)
 * - Named routes
 * - Closure routes
 *
 * @package codesaur\Http\Application\Example
 * @author Narankhuu
 * @since 1.0.0
 */
class ExampleRouter extends Router
{
    /**
     * ExampleRouter конструктор.
     *
     * Бүх жишээ маршрутуудыг энд бүртгэнэ.
     * Энэ нь codesaur/router багцын Router классын бүх боломжуудыг харуулдаг:
     * - Dynamic route parameters: {firstname}, {id}
     * - Typed parameters: {int:id}, {uint:b}, {float:number}
     * - Multi-method routes: POST_PUT, GET_POST_PUT_DELETE_OPTIONS
     * - Named routes: ->name('route_name')
     * - Closure routes: function($req) { ... }
     * - Controller routes: [Controller::class, 'action']
     */
    public function __construct()
    {
        // GET + name (route нэрлэх)
        $this->GET('/hello/{firstname}', [ExampleController::class, 'hello'])->name('hi');

        // POST|PUT handler
        $this->POST_PUT('/post-or-put', [ExampleController::class, 'post_put']);

        // илүү олон HTTP methods (GET|POST|PUT|DELETE|OPTIONS)
        $this->GET_POST_PUT_DELETE_OPTIONS('/echo/{singleword}', function (ServerRequestInterface $req) {
            echo '<br/>' . $req->getAttribute('params')['singleword'];
        })->name('echo');

        // Typed float parameter
        $this->GET('/float/{float:number}', [ExampleController::class, 'float'])->name('float');

        // Typed int + uint, sum example
        $this->GET('/sum/{int:a}/{uint:b}', function (ServerRequestInterface $req) {
            $a = $req->getAttribute('params')['a'];
            $b = $req->getAttribute('params')['b'];

            $sum = $a + $b;

            \var_dump($a, $b, $sum);
            echo "<br/>$a + $b = $sum";
        })->name('sum');
    }
}
