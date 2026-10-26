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
 * while the plugin is active there.  A site plugin activated on another site
 * is ignored, so one site can never load another's services.
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
     * Whether the site's plugin is active on the current blog, either on its
     * own or network-wide.
     *
     * Reads the current blog's options, so it follows `switch_to_blog()`.
     */
    public static function isActive(SiteKey $key): bool
    {
        return in_array(self::directory($key), self::activeDirectories(), true);
    }

    /**
     * Directories of every plugin active on the current blog.
     *
     * @return list<string>
     */
    public static function activeDirectories(): array
    {
        $active = get_option('active_plugins', []);
        $network = get_site_option('active_sitewide_plugins', []);

        $directories = [];
        $plugins = [
            ...(is_array($active) ? $active : []),
            ...(is_array($network) ? array_keys($network) : []),
        ];

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

    /**
     * Whether a site plugin is network-activated, which makes WordPress load
     * its PHP on every site.  Its providers and config still apply only to
     * its own site.
     */
    public static function isNetworkActive(SiteKey $key): bool
    {
        $network = get_site_option('active_sitewide_plugins', []);

        foreach (is_array($network) ? array_keys($network) : [] as $plugin) {
            if (is_string($plugin) && str_starts_with($plugin, self::directory($key) . '/')) {
                return true;
            }
        }

        return false;
    }
}
