<?php

namespace codesaur\Http\Application;

use Psr\Http\Message\ServerRequestInterface;

use codesaur\Globals\Post;

abstract class Controller
{
    private $_request;
    
    function __construct(ServerRequestInterface $request)
    {
        $this->_request = $request;
    }
    
    public function getRequest(): ServerRequestInterface
    {
        return $this->_request;
    }
    
    final function getParsedBody()
    {
        return $this->getRequest()->getParsedBody();
    }

    final function getBodyParam($name)
    {
        return $this->getRequest()->getParsedBody()[$name] ?? null;
    }

    final function getQueryParam($name)
    {
        return $this->getRequest()->getQueryParams()[$name] ?? null;
    }
    
    final function getPostParam($name, int $filter = FILTER_DEFAULT, $options = null)
    {
        $post = new Post();
        if ($post->has($name)) {
            return $post->value($name, $filter, $options);
        }
        
        return null;
    }
    
    final function isDevelopment(): bool
    {
        return defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT;
    }
}
