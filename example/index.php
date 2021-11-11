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

        $this->use(new ExampleRouter());

        $this->GET('/', [ExampleController::class, 'index']);

        $this->GET('/home', function ($req) { (new ExampleController($req))->index(); })->name('home');

        $this->GET('/hello/{utf8:firstname}/{utf8:lastname}', function (ServerRequestInterface $req)
        {
            $fullname = $req->getAttribute('params')['firstname'];
            $fullname .= ' ' . $req->getAttribute('params')['lastname'];

            (new ExampleController($req))->hello($fullname);
        })->name('hello');

        $this->POST('/hello/post', function (ServerRequestInterface $req)
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
        
        $this->use(function ($request, $handler)
        {
            $res = $handler->handle($request);
            $uri_path = rawurldecode($request->getUri()->getPath());
            $strip_lngth = strlen(dirname($request->getServerParams()['SCRIPT_NAME']));
            if ($strip_lngth <= 1) {
                $strip_lngth = 0;
            }
            $strip_lngth += strlen($request->getAttribute('pipe', ''));
            $target_path = $strip_lngth > 1 ? substr($uri_path, $strip_lngth) : $uri_path;
            if (empty($target_path)) {
                $target_path = '/';
            }
            $callback = $this->match($target_path, $request->getMethod());
            $callable = $callback->getCallable();
            echo '<br/><br/><span style="color:maroon">';
            if (!$callable instanceof Closure) {
                $controller = $callable[0];
                $action = $callable[1];
                echo "Application executing an action [{$action}] from controller [{$controller}]";
            } else {
                echo 'Application executing a callback';
            }
            echo '.</span><br/><br/>';

            return $res;
        });
    }
};

$application->handle((new ServerRequest())->initFromGlobal());
