<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

use function add_action;

/**
 * Registers post types and taxonomies from `config/post-types.php`.
 *
 * Replaces `roots/acorn-post-types`, using the same config format.  When that
 * package is also installed it is left in charge, so nothing is registered
 * twice; `site:check` reports the overlap.
 */
final class ContentTypesServiceProvider extends ServiceProvider
{
    public const string ACORN_POST_TYPES = 'Roots\AcornPostTypes\AcornPostTypesServiceProvider';

    public function register(): void
    {
        $this->app->singleton(ContentTypeRegistrar::class);
    }

    public function boot(): void
    {
        $acornPostTypes = ($this->app instanceof Application)
            ? $this->app->getProvider(self::ACORN_POST_TYPES)
            : null;
        if ($acornPostTypes !== null) {
            return;
        }

        // Priority 100, as in acorn-post-types, so other plugins' types exist.
        add_action('init', function (): void {
            $this->app->make(ContentTypeRegistrar::class)->registerAll();
        }, 100);
    }
}
