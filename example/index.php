<?php

namespace codesaur\Http\Application\Example;

/* DEV: v1.2021.03.15
 * 
 * This is an example script!
 */

use Error;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Router\Router;
use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Application\Controller;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;

$autoload = require_once '../vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', \dirname(__FILE__));

define('CODESAUR_DEVELOPMENT', true);

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

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
    }
}

class ExampleController extends Controller
{    
    public function index()
    {
        echo 'It works! [' .  self::class . ']';
    }
    
    public function hello(string $firstname)
    {
        $user = $firstname;
        $lastname = $this->getQueryParam('lastname');
        if (!empty($lastname)) {
            $user .= " $lastname";
        }
        
        echo "Hello $user!";
    }
    
    public function post_put()
    {
        $payload = $this->getParsedBody();
        
        if (empty($payload['firstname'])) {
            throw new Error('Invalid request!');
        }
        
        $user = $payload['firstname'];
        if (!empty($payload['lastname'])) {
            $user .= " {$payload['lastname']}";
        }
        
        $this->hello($user);
    }
    
    public function float(float $number)
    {
        var_dump($number);
    }
}

class BeforeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $pre_modified_request = $request->withAttribute('start-time', microtime());        
        return $handler->handle($pre_modified_request);
    }
}

class AfterMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request)->withHeader('end-time', microtime());
        
        echo sprintf('<hr>Request started at {%s} and finished in {%s}', $request->getAttribute('start-time'), current($response->getHeader('end-time')));
        
        return $response;
    }
}

$request = new ServerRequest();
$request->initFromGlobal();

$application = new Application();

$application->use(new ExceptionHandler());
$application->use(new BeforeMiddleware());
$application->use(new AfterMiddleware());

$application->any('/', ExampleController::class);

$application->merge(new ExampleRouter());

$application->get('/home', function ($req) { (new ExampleController($req))->index(); })->name('home');

$application->get('/hello/{string:firstname}/{lastname}', function (ServerRequestInterface $req) 
{
    $user = "{$req->getAttribute('firstname')} {$req->getAttribute('lastname')}";
    
    (new ExampleController($req))->hello($user);
})->name('hello');

$application->post('/hello/post', function (ServerRequestInterface $req)
{
    $payload = $req->getParsedBody();

    if (empty($payload['firstname'])) {
        throw new Error('Invalid request!');
    }

    $user = $payload['firstname'];
    if (!empty($payload['lastname'])) {
        $user .= " {$payload['lastname']}";
    }
    
    (new ExampleController($req))->hello($user);
});

$application->get('/float/{float:number}', [ExampleController::class, 'float'])->name('float');

$application->get('/sum/{int:a}/{uint:b}', function (ServerRequestInterface $req)
{
    $a = $req->getAttribute('a');
    $b = $req->getAttribute('b');

    $sum = $a + $b;

    var_dump($a, $b, $sum);
    
    echo "$a + $b = $sum";
})->name('sum');

$application->handle($request);
