<?php declare(strict_types=1);

namespace codesaur\Http\Application;

use Throwable;

interface ExceptionHandlerInterface
{
    public function exception(Throwable $throwable);
}
