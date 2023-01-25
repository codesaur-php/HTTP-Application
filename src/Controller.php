<?php

namespace codesaur\Http\Application;

use Psr\Http\Message\ServerRequestInterface;

abstract class Controller
{
    protected ServerRequestInterface $request;
    
    function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }
    
    final public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
    
    final function getParsedBody(): array
    {
        $parsedBody = $this->getRequest()->getParsedBody();
        if (empty($parsedBody)) {
            return [];
        }
        
        return (array) $parsedBody;
    }

    final function getQueryParams(): array
    {
        return $this->getRequest()->getQueryParams();
    }

    final function getAttributes(): array
    {
        return $this->getRequest()->getAttributes();
    }

    final function getAttribute(string $name, $default = null)
    {
        return $this->getRequest()->getAttribute($name, $default);
    }
    
    final function isDevelopment(): bool
    {
        return defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT;
    }
}
