<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Closure;
use Kleinweb\Lib\Tenancy\Exceptions\SitePluginNetworkActivated;
use Throwable;

use function add_action;
use function add_filter;
use function array_keys;
use function esc_html;
use function is_array;
use function is_string;
use function wp_die;

/**
 * Refuses network activation of site plugins.
 *
 * A site plugin holds one site's code; the network-wide code is the
 * application itself (the mu-plugin that boots Acorn).  A network-activated
 * site plugin would never apply anyway ({@see SitePlugin::isActive()}), so
 * activation is stopped rather than left to look as if it worked:
 *
 * - `activate_plugin`: an interactive network activation, from the network
 *   admin or `wp plugin activate --network`, ends in `wp_die()` before
 *   WordPress records it.
 * - `pre_update_site_option_active_sitewide_plugins`: any other write that
 *   adds a site plugin (silent activation, direct option updates) has it
 *   removed, and the attempt is reported.
 *
 * Site plugins already network-activated are left alone here and reported
 * by `site:check`.
 */
final class SitePluginGuard
{
    /**
     * @param Closure(Throwable): void $report
     */
    public function __construct(private readonly Closure $report) {}

    public function register(): void
    {
        add_action('activate_plugin', $this->refuseActivation(...), 0, 2);
        add_filter(
            'pre_update_site_option_active_sitewide_plugins',
            $this->filterNetworkPlugins(...),
            0,
            2,
        );
    }

    public function refuseActivation(mixed $plugin, mixed $networkWide = false): void
    {
        if (!$networkWide || !is_string($plugin) || !SitePlugin::isSitePlugin($plugin)) {
            return;
        }

        wp_die(
            esc_html((new SitePluginNetworkActivated($plugin))->getMessage()),
            'Site plugin not activated',
            ['response' => 403, 'back_link' => true],
        );
    }

    /**
     * Drop site plugins newly added to the network-active list.
     */
    public function filterNetworkPlugins(mixed $value, mixed $oldValue = []): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $old = is_array($oldValue) ? $oldValue : [];
        foreach (array_keys($value) as $plugin) {
            if (!is_string($plugin) || !SitePlugin::isSitePlugin($plugin) || isset($old[$plugin])) {
                continue;
            }

            unset($value[$plugin]);
            ($this->report)(new SitePluginNetworkActivated($plugin));
        }

        return $value;
    }
}
