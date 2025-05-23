<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Illuminate\Support\ServiceProvider as ServiceProviderBase;
use Kleinweb\Lib\Hooks\Traits\Hookable;

/**
 * Service provider base class providing framework-specific features.
 */
abstract class ServiceProvider extends ServiceProviderBase
{
    use Hookable;

    public function register(): void
    {
        parent::register();

        $this->booted($this->registerHooks(...));
    }

    public function boot(): void
    {
        // NOTE: Just a placeholder for now.
    }
}
