<?php

// SPDX-FileCopyrightText: (C) 2025 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Roots\Acorn\Sage\SageServiceProvider as SageServiceProviderBase;
use Kleinweb\Lib\Hooks\Traits\Hookable;

abstract class SageServiceProvider extends SageServiceProviderBase
{
    use Hookable;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->booted($this->registerHooks(...));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
