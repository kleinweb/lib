<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later


declare(strict_types=1);

namespace Kleinweb\Lib\Hooks\Contracts;

interface HookRegistrar
{
    /**
     * @param callable(): mixed $callable
     */
    public function register(callable $callable, int $acceptedArgs = 1): void;
}
