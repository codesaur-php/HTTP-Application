<?php

namespace codesaur\Http\Application\Example;

/* DEV: v2.2021.08.20
 * 
 * This is an example script!
 */

define('CODESAUR_DEVELOPMENT', true);

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

use Error;
use Closure;

use Psr\Http\Message\ServerRequestInterface;

use codesaur\Router\Route;
use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;

$autoload = require_once '../vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', dirname(__FILE__));

$application = new class extends Application
{
    function __construct()
    {
        parent::__construct();
        
        $this->use(new ExceptionHandler());
        
        $this->use(new BeforeMiddleware());
        $this->use(new AfterMiddleware());
        $this->use(new OnionMiddleware());

        $this->any('/', ExampleController::class);

        $this->merge(new ExampleRouter());

        $this->get('/home', function ($req) { (new ExampleController($req))->index(); })->name('home');

        $this->get('/hello/{string:firstname}/{lastname}', function (ServerRequestInterface $req)
        {
            $user = "{$req->getAttribute('firstname')} {$req->getAttribute('lastname')}";

            (new ExampleController($req))->hello($user);
        })->name('hello');

        $this->post('/hello/post', function (ServerRequestInterface $req)
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
    }
    
    public function matchRoute(ServerRequestInterface $request): Route
    {
        $route = parent::matchRoute($request);
        
        $callback = $route->getCallback();
        if (!$callback instanceof Closure) {
            $controller = $callback[0];
            $action = $callback[1] ?? 'index';
            echo "<span style=\"color:maroon\">Application executing an action [{$action}] from controller [{$controller}].</span><br/><br/>";
        } else {
            echo "<span style=\"color:maroon\">Application executing a callback.</span><br/><br/>";
        }
        
        return $route;
    }
};

$application->handle((new ServerRequest())->initFromGlobal());
