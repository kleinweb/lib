<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Kleinweb\Lib\Support\Tenancy;
use Closure;

/**
 * Make WordPress load each site-specific plugin on that site only.
 *
 * Invoke {@see SitePluginLoader::register()} from an mu-plugin before WordPress fires the `plugins_loaded` action.
 *
 * The current site's site-specific plugin should be named according to the format `<PRJ-SLUG>-site-<SITE-KEY>`.
 * For example, in the "seesaw" project, for a site whose key is
 * "somesite", its site-plugin should be named "seesaw-site-somesite".
 */
final class SitePluginLoader
{
    private const array READ = [self::class, 'addOwnPlugin'];

    private const array WRITE = [self::class, 'removeSitePlugins'];

    private static bool $isSuspended = false;

    public static function register(): void
    {
        if (self::isRegistered() || ! Tenancy::isMultisite()) {
            return;
        }

        add_filter('option_active_plugins', self::READ, 0);
        add_filter('pre_update_option_active_plugins', self::WRITE, 0);
    }

    public static function isRegistered(): bool
    {
        return has_filter('option_active_plugins', self::READ) !== false;
    }

    /**
     * The current site's own site-plugin, by basename, if it exists.
     */
    public static function ownPlugin(): ?string
    {
        $key = SitePlugin::keyForSite(get_current_blog_id());

        return (($key !== null) && SitePlugin::exists($key)) ? SitePlugin::basename($key) : null;
    }

    /**
     * Invoke a callback without the current site's site-plugin active.
     *
     * @template TReturn
     *
     * @param Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public static function withoutOwnPlugin(Closure $callback): mixed
    {
        $prev = self::$isSuspended;

        self::$isSuspended = true;

        try {
            return $callback();
        } finally {
            self::$isSuspended = $prev;
        }
    }

    /**
     * @internal `option_active_plugins` filter
     */
    public static function addOwnPlugin(mixed $value): mixed
    {
        if (self::$isSuspended) {
            return $value;
        }

        $plugin = self::ownPlugin();
        if ($plugin === null) {
            return $value;
        }

        $plugins = is_array($value) ? array_values($value) : [];
        if (! in_array($plugin, $plugins, true)) {
            $plugins[] = $plugin;
            sort($plugins);
        }

        return $plugins;
    }

    /**
     * Keep site plugins out of the stored list of active plugins.
     *
     * @internal `pre_update_option_active_plugins` filter
     */
    public static function removeOwnPlugins(mixed $value): mixed
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
