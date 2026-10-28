<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Closure;
use Kleinweb\Lib\Tenancy\Exceptions\SitePluginActivation;
use Throwable;

use function add_action;
use function add_filter;
use function array_keys;
use function esc_html;
use function in_array;
use function is_array;
use function is_string;
use function wp_die;

/**
 * Refuses activation and deactivation of site plugins.
 *
 * Site plugins load automatically on their own site
 * ({@see SitePluginLoader}); activating one would at best do nothing and at
 * worst load it where it does not belong, so:
 *
 * - `map_meta_cap`: nobody has `activate_plugin` or `deactivate_plugin` for
 *   a site plugin, so the plugins screens offer neither.
 * - `activate_plugin`: an activation that does not check capabilities
 *   (WP-CLI, code) ends in `wp_die()` before WordPress records it.
 * - `pre_update_site_option_active_sitewide_plugins`: a site plugin added to
 *   the network-active list any other way is removed, and reported.
 *   ({@see SitePluginLoader} keeps them out of per-site lists.)
 *
 * Activations already stored are reported by `site:check`.
 */
final class SitePluginGuard
{
    private const array CAPABILITIES = ['activate_plugin', 'deactivate_plugin'];

    /**
     * @param Closure(Throwable): void $report
     */
    public function __construct(private readonly Closure $report) {}

    public function register(): void
    {
        add_filter('map_meta_cap', $this->denyCapabilities(...), 10, 4);
        add_action('activate_plugin', $this->refuseActivation(...), 0, 2);
        add_filter(
            'pre_update_site_option_active_sitewide_plugins',
            $this->filterNetworkPlugins(...),
            0,
            2,
        );
    }

    /**
     * @param array<array-key, mixed> $caps
     * @param array<array-key, mixed> $args
     *
     * @return array<array-key, mixed>
     */
    public function denyCapabilities(
        array $caps,
        mixed $cap,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter -- Precedes $args in the filter.
        mixed $userId = 0,
        array $args = [],
    ): array {
        $plugin = $args[0] ?? null;

        return in_array($cap, self::CAPABILITIES, true)
            && is_string($plugin)
            && SitePlugin::isSitePlugin($plugin)
            ? ['do_not_allow']
            : $caps;
    }

    public function refuseActivation(mixed $plugin, mixed $networkWide = false): void
    {
        if (!is_string($plugin) || !SitePlugin::isSitePlugin($plugin)) {
            return;
        }

        wp_die(
            esc_html((new SitePluginActivation($plugin, (bool) $networkWide))->getMessage()),
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
            ($this->report)(new SitePluginActivation($plugin, true));
        }

        return $value;
    }
}
