<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Contracts;

/**
 * Isolates per-blog state, for every blog in the network.
 *
 * Unlike {@see SiteBootstrapper}, which applies only to sites with a key, a
 * blog bootstrapper runs for every blog WordPress switches to, keyed or not.
 * It is idempotent: called with the target blog ID on every `switch_blog`,
 * including Core's own internal switches (for example inside
 * `get_home_url()`), so it must be cheap and must never throw.
 */
interface BlogBootstrapper
{
    public function apply(int $blogId): void;
}
