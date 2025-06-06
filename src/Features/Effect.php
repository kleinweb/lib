<?php

// SPDX-FileCopyrightText: (C) 2023-2025 Alley <info@alley.com>
// SPDX-FileCopyrightText: (C) 2025 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Features;

use Kleinweb\Lib\Types\Feature;

/**
 * Boot a feature as an effect of a condition being true.
 */
final class Effect implements Feature
{
    /**
     * The condition to check.
     *
     * @var callable
     */
    private $when;

    /**
     * Constructor.
     *
     * @param callable $when the condition to check
     * @param Feature  $then the feature to boot if the condition is met
     */
    public function __construct(
        callable $when,
        private readonly Feature $then,
    ) {
        $this->when = $when;
    }

    /**
     * Boot the feature.
     */
    public function boot(): void
    {
        if (($this->when)() === true) {
            $this->then->boot();
        }
    }
}
