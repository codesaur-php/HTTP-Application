<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;

use codesaur\Router\Router;

class ExampleRouter extends Router
{
    function __construct()
    {
        $this->GET('/hello/{utf8:firstname}', [ExampleController::class, 'hello'])->name('hi');
        
        $this->POST_PUT('/post-or-put', [ExampleController::class, 'post_put']);
        
        $this->GET_POST_PUT_DELETE_OPTIONS('/echo/{singleword}', function (ServerRequestInterface $req)
        {
            echo '<br/>' . $req->getAttribute('params')['singleword'];
        })->name('echo');
        
        $this->GET('/float/{float:number}', [ExampleController::class, 'float'])->name('float');

        $this->GET('/sum/{int:a}/{uint:b}', function (ServerRequestInterface $req)
        {
            $a = $req->getAttribute('params')['a'];
            $b = $req->getAttribute('params')['b'];

            $sum = $a + $b;

            var_dump($a, $b, $sum);

            echo "<br/>$a + $b = $sum";
        })->name('sum');
    }
}
