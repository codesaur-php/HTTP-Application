<?php

namespace codesaur\Http\Application\Example;

use Error;

use codesaur\Http\Application\Controller;

class ExampleController extends Controller
{    
    public function index()
    {
        echo '<br/>It works! [' .  self::class . ']<br/><br/>';
    }
    
    public function hello(string $firstname)
    {
        $user = $firstname;
        $params = $this->getQueryParams();
        if (!empty($params['lastname'])) {
            $user .= " {$params['lastname']}";
        }
        
        echo "<br/>Hello $user!";
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
