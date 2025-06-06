<?php

// SPDX-FileCopyrightText: (C) 2023-2025 Alley <info@alley.com>
// SPDX-FileCopyrightText: (C) 2025 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Features;

use Kleinweb\Lib\Types\Feature;

/**
 * Boot features in a guaranteed order.
 */
final class Ordered implements Feature
{
    /**
     * @param Feature $first the first feature to boot
     * @param Feature $then  the feature to boot after
     */
    public function __construct(
        private readonly Feature $first,
        private readonly Feature $then,
    ) {}

    public function boot(): void
    {
        $this->first->boot();
        $this->then->boot();
    }
}
