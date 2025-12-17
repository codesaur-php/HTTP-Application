<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;
use codesaur\Router\Router;

/**
 * ExampleRouter Class
 *
 * Энэ бол codesaur/router ашиглан маршрутууд тодорхойлсон router жишээ.
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
