<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Kleinweb\Lib\Tenancy\Contracts;

/**
 * Isolate state across all sites.
 *
 * Unlike {@see TenantBootstrapper}, which applies only to sites with a site key, a site bootstrapper runs for each switched-to site, keyed or not.
 *
 * {@see SiteBootstrapper::apply()} will be invoked on every `switch_blog` event,
 * including in WordPress Core internals (e.g. {@see \get_home_url()}).
 * For this reason, invocation must be inexpensive and must never throw.
 */
interface SiteBootstrapper
{
    public function apply(int $siteId): void;
}
