<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;

use function get_current_blog_id;

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
 * (or, for a job that implements {@see Queue\ShouldRunOnSiteWorker}, with
 * {@see Queue\RunsOnSiteWorker}, which picks the dispatching site's queue),
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

    /**
     * The queue of the current blog, following `switch_to_blog()`.
     *
     * @throws InvalidSiteKey if the current blog has no key
     */
    public static function current(): string
    {
        $key = SitePlugin::keyForBlog(get_current_blog_id())
            ?? throw InvalidSiteKey::forValue('');

        return self::name($key);
    }
}
