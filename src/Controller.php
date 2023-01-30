<?php

namespace codesaur\Http\Application;

use Psr\Http\Message\ServerRequestInterface;

abstract class Controller
{
    protected ServerRequestInterface $request;
    
    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }
    
    public final function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
    
    public final function getParsedBody(): array
    {
        $parsedBody = $this->getRequest()->getParsedBody();
        
        if ($parsedBody == null) {
            return [];
        }
        
        return (array) $parsedBody;
    }

    public final function getQueryParams(): array
    {
        return $this->getRequest()->getQueryParams();
    }

    public final function getAttributes(): array
    {
        return $this->getRequest()->getAttributes();
    }

    public final function getAttribute(string $name, $default = null)
    {
        return $this->getRequest()->getAttribute($name, $default);
    }
    
    public final function isDevelopment(): bool
    {
        return defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT;
    }
}
