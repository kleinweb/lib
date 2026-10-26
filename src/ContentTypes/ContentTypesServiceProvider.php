<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

use Illuminate\Support\ServiceProvider;

use function add_action;

/**
 * Registers post types and taxonomies from `config/post-types.php`, per site.
 *
 * Takes the place of `roots/acorn-post-types`, which registers one site's
 * types for the whole process; the config format is the same.  Do not
 * install both.
 */
final class ContentTypesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentTypeRegistrar::class);
    }

    public function boot(): void
    {
        // Late on `init`, so types registered by other plugins already exist.
        add_action('init', function (): void {
            $this->app->make(ContentTypeRegistrar::class)->registerAll();
        }, 100);
    }
}
