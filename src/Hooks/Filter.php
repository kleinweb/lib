<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Hooks;

use Attribute;

// phpcs:disable SlevomatCodingStandard.Classes.RequireAbstractOrFinal

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Filter
{
    public function __construct(
        private readonly string $hook,
        private readonly int $priority = 10,
    ) {}

    /**
     * @param callable $callback hook function
     * @param int      $numArgs  number of parameters accepted by the callback
     */
    public function register(callable $callback, int $numArgs = 1): void
    {
        add_filter($this->hook, $callback, $this->priority, $numArgs);
    }
}
