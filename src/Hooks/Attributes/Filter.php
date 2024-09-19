<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Hooks\Attributes;

use Kleinweb\Lib\Hooks\Contracts\HookRegistrar;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Filter implements HookRegistrar
{
    public function __construct(
        public string $hook,
        public int $priority = 10,
    ) {}

    public function register(callable $callable, int $acceptedArgs = 1): void
    {
        add_filter($this->hook, $callable, $this->priority, $acceptedArgs);
    }
}
