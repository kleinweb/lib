<?php

// SPDX-FileCopyrightText: (C) 2023-2025 Alley <info@alley.com>
// SPDX-FileCopyrightText: (C) 2025 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Features;

use Kleinweb\Lib\Types\Feature;
use Kleinweb\Lib\Types\Features;

/**
 * Group many features.
 */
final class Group implements Features
{
    /**
     * Collected features.
     *
     * @var Feature[]
     */
    private array $features;

    /**
     * Set up.
     *
     * @param Feature ...$features Features.
     */
    public function __construct(Feature ...$features)
    {
        $this->features = $features;
    }

    /**
     * Boot the feature.
     */
    public function boot(): void
    {
        foreach ($this->features as $feature) {
            $feature->boot();
        }
    }

    /**
     * Include a feature.
     *
     * @param Feature $feature feature to include
     */
    public function include(Feature $feature): void
    {
        $this->features[] = $feature;
    }
}
