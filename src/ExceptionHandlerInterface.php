<?php

namespace codesaur\Http\Application;

interface ExceptionHandlerInterface
{
    public function exception(\Throwable $throwable);
}
