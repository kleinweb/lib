<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Events;

use Kleinweb\Lib\Database\Models\Site;

/**
 * Site bootstrappers followed a `switch_to_blog()` or `restore_current_blog()`.
 *
 * Providers do not change on a switch; only bootstrapped state (such as
 * config) does.  `$site` is null when the target site has no key.  Also fired
 * as the WordPress action `kleinweb/tenancy/switched`.
 */
final readonly class SiteSwitched
{
    public function __construct(
        public ?Site $site,
        public ?Site $previous,
    ) {}
}
