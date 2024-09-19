<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Hooks\Contracts;

interface HookRegistrar
{
    /**
     * @param callable(): mixed $callable
     */
    public function register(callable $callable, int $acceptedArgs = 1): void;
}
