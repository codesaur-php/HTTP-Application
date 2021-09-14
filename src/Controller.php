<?php

namespace codesaur\Http\Application;

use Psr\Http\Message\ServerRequestInterface;

abstract class Controller
{
    private $_request;
    
    function __construct(ServerRequestInterface $request)
    {
        $this->setRequest($request);
    }
    
    final public function getRequest(): ServerRequestInterface
    {
        return $this->_request;
    }
    
    final public function setRequest(ServerRequestInterface $request)
    {
        $this->_request = $request;
    }
    
    final function getParsedBody()
    {
        return $this->getRequest()->getParsedBody();
    }

    final function getBodyParam($name)
    {
        return $this->getRequest()->getParsedBody()[$name] ?? null;
    }

    final function getQueryParams(): array
    {
        return $this->getRequest()->getQueryParams();
    }

    final function getQueryParam($name)
    {
        return $this->getRequest()->getQueryParams()[$name] ?? null;
    }
    
    final function getAttributes(): array
    {
        return $this->getRequest()->getAttributes();
    }

    final function getAttribute($name, $default = null)
    {
        return $this->getRequest()->getAttribute($name, $default);
    }
    
    final function getPostParam($name, int $filter = FILTER_DEFAULT, $options = null)
    {
        if (!filter_has_var(INPUT_POST, $name)) {
            return null;
        }
        
        return filter_input(INPUT_POST, $name, $filter, $options);
    }
    
    final function isDevelopment(): bool
    {
        return defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT;
    }
}
