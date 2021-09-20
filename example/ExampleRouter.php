<?php

namespace codesaur\Http\Application\Example;

use Psr\Http\Message\ServerRequestInterface;

use codesaur\Router\Router;

class ExampleRouter extends Router
{
    function __construct()
    {        
        $this->get('/hello/{firstname}', [ExampleController::class, 'hello'])->name('hi');
        
        $this->map(['POST', 'PUT'], '/post-or-put', [ExampleController::class, 'post_put']);
        
        $this->any('/echo/{singleword}', function (ServerRequestInterface $req)
        {
            echo $req->getAttribute('singleword');
        })->name('echo');
        
        $this->get('/float/{float:number}', [ExampleController::class, 'float'])->name('float');

        $this->get('/sum/{int:a}/{uint:b}', function (ServerRequestInterface $req)
        {
            $a = $req->getAttribute('a');
            $b = $req->getAttribute('b');

            $sum = $a + $b;

            var_dump($a, $b, $sum);

            echo "$a + $b = $sum";
        })->name('sum');
    }
}
