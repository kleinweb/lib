<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;

/**
 * Queue names for sites that run their own workers.
 *
 * By default one worker serves the whole network: Acorn adds the blog ID to
 * each job and switches to that blog while it runs, and the config overlay
 * follows the switch.  That worker has only its own site's providers, so a
 * job that needs another site's bindings goes on that site's queue instead:
 *
 * ```php
 * dispatch(new RebuildIndex())->onQueue(SiteQueue::name($site));
 * ```
 *
 * and is processed by a worker started for that site:
 *
 * ```sh
 * wp --url=<site url> acorn queue:work --queue=site-news-hub
 * ```
 */
final class SiteQueue
{
    public const string PREFIX = 'site-';

    /**
     * @throws InvalidSiteKey if the site has no key
     */
    public static function name(Site|SiteKey|string $site): string
    {
        $key = match (true) {
            $site instanceof Site => $site->key() ?? throw InvalidSiteKey::forValue(''),
            $site instanceof SiteKey => $site,
            default => SiteKey::from($site),
        };

        return self::PREFIX . $key->value;
    }
}
