<?php

namespace codesaur\Http\Application\Example;

use codesaur\Http\Application\Controller;

/**
 * Class ExampleController
 *
 * Багцын Controller base class ашигласан демо controller.
 * GET/POST body, query, attributes зэрэг PSR-7 request өгөгдөлд хандахыг харуулдаг.
 */
class ExampleController extends Controller
{
    /**
     * GET /
     */
    public function index()
    {
        echo '<br/>It works! [' . self::class . ']<br/><br/>';
    }

    /**
     * GET /hello/{firstname}
     */
    public function hello(string $firstname)
    {
        $user = $firstname;
        $params = $this->getQueryParams();
        if (!empty($params['lastname'])) {
            $user .= " {$params['lastname']}";
        }

        echo "<br/>Hello $user!";
    }

    /**
     * POST|PUT /post-or-put
     */
    public function post_put()
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
     * GET /float/{float:number}
     */
    public function float(float $number)
    {
        \var_dump($number);
    }
}
