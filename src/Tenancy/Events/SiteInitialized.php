<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Events;

use Kleinweb\Lib\Database\Models\Site;

/**
 * The request's site has its providers registered and its config applied.
 *
 * Also fired as the WordPress action `kleinweb/tenancy/initialized`.
 */
final readonly class SiteInitialized
{
    public function __construct(public Site $site) {}
}
