<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use function array_keys;
use function constant;
use function get_option;
use function get_site_option;
use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * A site's own plugin: `wp-content/plugins/kleinweb-site-{key}/`.
 *
 * Everything specific to one site that is not in its theme lives here: its
 * providers (declared in the plugin's `composer.json`) and its config
 * overlays (`config/*.php`).  The application itself holds no site-specific
 * code.
 *
 * WordPress loads the plugin as usual; the application registers its
 * providers and applies its config only on a request for that site, and only
 * while the plugin is activated there.  A site plugin activated on another
 * site is ignored, so one site can never load another's services.  Network
 * activation is refused ({@see SitePluginGuard}) and never counts.
 */
final class SitePlugin
{
    public const string PREFIX = 'kleinweb-site-';

    public static function directory(SiteKey $key): string
    {
        return self::PREFIX . $key->value;
    }

    public static function path(SiteKey $key): string
    {
        return constant('WP_PLUGIN_DIR') . '/' . self::directory($key);
    }

    public static function configPath(SiteKey $key): string
    {
        return self::path($key) . '/config';
    }

    /**
     * Default root namespace for a site plugin, e.g. `Kleinweb\Sites\NewsHub\`.
     */
    public static function namespace(SiteKey $key): string
    {
        return 'Kleinweb\\Sites\\' . $key->studly() . '\\';
    }

    /**
     * The site key a plugin directory name belongs to, if it is a site plugin.
     */
    public static function keyFor(string $directory): ?SiteKey
    {
        return str_starts_with($directory, self::PREFIX)
            ? SiteKey::tryFrom(substr($directory, strlen(self::PREFIX)))
            : null;
    }

    /**
     * Whether a plugin basename (`dir/file.php`) belongs to a site plugin.
     */
    public static function isSitePlugin(string $basename): bool
    {
        return str_starts_with($basename, self::PREFIX);
    }

    /**
     * Whether the site's plugin is active on the current blog.
     *
     * Only per-site activation counts.  A site plugin must never be
     * network-activated (the application itself is the network-wide code);
     * if one is, it applies nowhere, and `site:check` reports it.
     *
     * Reads the current blog's options, so it follows `switch_to_blog()`.
     */
    public static function isActive(SiteKey $key): bool
    {
        return in_array(self::directory($key), self::activeDirectories(), true);
    }

    /**
     * Directories of the plugins activated on the current blog itself, not
     * network-wide.
     *
     * @return list<string>
     */
    public static function activeDirectories(): array
    {
        $active = get_option('active_plugins', []);

        return self::directories(is_array($active) ? $active : []);
    }

    /**
     * Site plugins that are network-activated, by basename.
     *
     * @return list<string>
     */
    public static function networkActivated(): array
    {
        $network = get_site_option('active_sitewide_plugins', []);

        $plugins = [];
        foreach (is_array($network) ? array_keys($network) : [] as $plugin) {
            if (is_string($plugin) && self::isSitePlugin($plugin)) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    /**
     * @param array<array-key, mixed> $plugins
     *
     * @return list<string>
     */
    private static function directories(array $plugins): array
    {
        $directories = [];

        foreach ($plugins as $plugin) {
            if (!is_string($plugin)) {
                continue;
            }

            $slash = strpos($plugin, '/');
            if ($slash !== false) {
                $directories[] = substr($plugin, 0, $slash);
            }
        }

        return $directories;
    }
}
