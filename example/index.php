<?php

namespace codesaur\Http\Application\Example;

/* DEV: v5.2024.09.20
 * 
 * Example script for demonstrating the usage of codesaur/http-application package.
 *
 * Энэ файл нь:
 *  - Autoload тохируулах
 *  - Application үүсгэх
 *  - Middleware-үүд холбох
 *  - Router бүртгэх
 *  - Controller болон Closure маршрутууд тодорхойлох
 *  - HTTP хүсэлтийг PSR-7 ServerRequest ашиглан боловсруулах
 *
 * Энэ нь багцыг бодит төсөлд хэрхэн ашиглахыг харуулах демо юм.
 */

\ini_set('display_errors', 'On');
\error_reporting(\E_ALL);

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Application\Application;
use codesaur\Http\Application\ExceptionHandler;

$autoload = require_once '../vendor/autoload.php';
$autoload->addPsr4(__NAMESPACE__ . '\\', \dirname(__FILE__));

/**
 * Application demo класс — middleware, route, controller-ийг бүртгэдэг.
 */
$application = new class extends Application
{
    public function __construct()
    {
        parent::__construct();

        // Exception handler бүртгэх
        $this->use(new ExceptionHandler());

        // Middleware-үүд
        $this->use(new BeforeMiddleware());
        $this->use(new AfterMiddleware());
        $this->use(new OnionMiddleware());

        // Router бүртгэх
        $this->use(new ExampleRouter());

        // Controller route
        $this->GET('/', [ExampleController::class, 'index']);

        // Closure + Controller route
        $this->GET('/home', function ($req) {
            (new ExampleController($req))->index();
        })->name('home');

        // Dynamic params
        $this->GET('/hello/{firstname}/{lastname}', function (ServerRequestInterface $req) {
            $fullname = $req->getAttribute('params')['firstname']
                      . ' ' . $req->getAttribute('params')['lastname'];

            (new ExampleController($req))->hello($fullname);
        })->name('hello');

        // POST route
        $this->POST('/hello/post', function (ServerRequestInterface $req) {
            $payload = $req->getParsedBody();

            if (empty($payload['firstname'])) {
                throw new \Error('Invalid request!');
            }

            $user = $payload['firstname'];
            if (!empty($payload['lastname'])) {
                $user .= " {$payload['lastname']}";
            }

            (new ExampleController($req))->hello($user);
        });

        /**
         * Tail middleware – route гүйцэтгэлийн мэдээлэл хэвлэх.
         */
        $this->use(function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
            $res = $handler->handle($request);

            $uri_path = \rawurldecode($request->getUri()->getPath());
            $script_path = $request->getServerParams()['SCRIPT_TARGET_PATH'] ?? null;

            if (!isset($script_path)) {
                $script_path = \dirname($request->getServerParams()['SCRIPT_NAME']);
                if ($script_path == '\\' || $script_path == '/' || $script_path == '.') {
                    $script_path = null;
                }
            }
            if (!empty($script_path)) {
                $uri_path = \substr($uri_path, \strlen($script_path));
            }
            if (empty($uri_path)) {
                $uri_path = '/';
            }

            $callback = $this->match($uri_path, $request->getMethod());
            $callable = $callback->getCallable();

            echo '<br/><br/><span style="color:maroon">';
            if (!$callable instanceof \Closure) {
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

// Application-г ажиллуулах
$application->handle((new ServerRequest())->initFromGlobal());
