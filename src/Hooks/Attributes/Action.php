<?php

// SPDX-FileCopyrightText: (C) 2022 Alley <mantle@alley.com>
// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Hooks\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Action
{
    public function __construct(public string $hook, public int $priority = 10) {}

    /**
     * @param callable(): void $callable
     */
    public function register(callable $callable, int $acceptedArgs = 1): void
    {
        add_action($this->hook, $callable, $this->priority, $acceptedArgs);
    }
}
