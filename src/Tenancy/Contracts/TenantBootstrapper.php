<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Kleinweb\Lib\Tenancy\Contracts;

use Kleinweb\Lib\Database\Models\Site;

/**
 * Scope a part of the application to the current site.
 */
interface TenantBootstrapper
{
    public function bootstrap(Site $site): void;

    /**
     * Unwind effects of {@see TenantBootstrapper::bootstrap()}.
     */
    public function revert(): void;
}
