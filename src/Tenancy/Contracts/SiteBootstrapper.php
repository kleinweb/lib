<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Contracts;

use Kleinweb\Lib\Database\Models\Site;

/**
 * Makes part of the application follow the current site.
 *
 * Bootstrappers run when the request's site is initialized and again on
 * every `switch_blog`.  Unlike providers, their effects must be reversible.
 */
interface SiteBootstrapper
{
    public function bootstrap(Site $site): void;

    /**
     * Undo everything {@see SiteBootstrapper::bootstrap()} changed.
     */
    public function revert(): void;
}
