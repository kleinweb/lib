<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Closure;

use function add_filter;
use function array_values;
use function function_exists;
use function get_current_blog_id;
use function has_filter;
use function in_array;
use function is_array;
use function is_multisite;
use function is_string;
use function sort;

/**
 * Makes WordPress load each site's own plugin on that site only.
 *
 * Call {@see SitePluginLoader::register()} from an mu-plugin, before
 * WordPress loads plugins (the project's require-plugins mu-plugin):
 *
 * ```php
 * Kleinweb\Lib\Tenancy\SitePluginLoader::register();
 * ```
 *
 * The current blog's site plugin (`kleinweb-site-{its key}`), if it exists,
 * is added to that blog's `active_plugins` as read, never as stored.
 * WordPress then loads it with the other plugins, and `is_plugin_active()`
 * and the plugins screen agree.  The list follows `switch_to_blog()`: every
 * blog sees its own site plugin and no other.
 *
 * Without this, a site plugin's providers and config still apply to its
 * site, since the application decides those by key; only its plugin file
 * is not loaded.  `site:check` reports a project that does not call it.
 */
final class SitePluginLoader
{
    private static bool $registered = false;

    private static bool $suspended = false;

    public static function register(): void
    {
        if (self::$registered || !function_exists('is_multisite') || !is_multisite()) {
            return;
        }

        self::$registered = true;

        add_filter('option_active_plugins', self::addOwnPlugin(...), 0);
        add_filter('pre_update_option_active_plugins', self::removeSitePlugins(...), 0);
    }

    public static function isRegistered(): bool
    {
        return self::$registered && has_filter('option_active_plugins') !== false;
    }

    /**
     * The current blog's own site plugin, by basename, if it has one.
     */
    public static function ownPlugin(): ?string
    {
        $key = SitePlugin::keyForBlog(get_current_blog_id());

        return ($key !== null && SitePlugin::exists($key)) ? SitePlugin::basename($key) : null;
    }

    /**
     * Run a callback that reads `active_plugins` without the own site
     * plugin added, to see what is stored (or added by other code).
     *
     * @template TReturn
     *
     * @param Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public static function withoutOwnPlugin(Closure $callback): mixed
    {
        $previous = self::$suspended;
        self::$suspended = true;

        try {
            return $callback();
        } finally {
            self::$suspended = $previous;
        }
    }

    /**
     * @internal `option_active_plugins` filter
     */
    public static function addOwnPlugin(mixed $value): mixed
    {
        if (self::$suspended) {
            return $value;
        }

        $plugin = self::ownPlugin();
        if ($plugin === null) {
            return $value;
        }

        $plugins = is_array($value) ? array_values($value) : [];
        if (!in_array($plugin, $plugins, true)) {
            $plugins[] = $plugin;
            sort($plugins);
        }

        return $plugins;
    }

    /**
     * Keep site plugins out of the stored list.  WordPress saves the list it
     * read, which includes the one added above, whenever a plugin is
     * activated or deactivated.
     *
     * @internal `pre_update_option_active_plugins` filter
     */
    public static function removeSitePlugins(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $plugins = [];
        foreach ($value as $plugin) {
            if (!is_string($plugin) || !SitePlugin::isSitePlugin($plugin)) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }
}
