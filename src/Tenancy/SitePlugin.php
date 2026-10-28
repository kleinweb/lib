<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use function array_keys;
use function constant;
use function get_site_meta;
use function get_option;
use function get_site_option;
use function is_array;
use function is_file;
use function is_string;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * A site's own plugin: `wp-content/plugins/kleinweb-site-{key}/`.
 *
 * Everything specific to one site that is not in its theme lives here: its
 * providers (declared in the plugin's `composer.json`) and its config
 * overlays (`config/*.php`).  The application itself holds no site-specific
 * code.
 *
 * A site plugin is never activated.  It applies to the site whose key it is
 * named for whenever it exists: the application registers its providers and
 * applies its config on that site only, and {@see SitePluginLoader} makes
 * WordPress load its plugin file there and nowhere else.  Activation, per
 * site or network-wide, is refused ({@see SitePluginGuard}).
 */
final class SitePlugin
{
    public const string PREFIX = 'kleinweb-site-';

    public static function directory(SiteKey $key): string
    {
        return self::PREFIX . $key->value;
    }

    /**
     * The plugin basename WordPress uses, `kleinweb-site-{key}/kleinweb-site-{key}.php`.
     */
    public static function basename(SiteKey $key): string
    {
        return self::directory($key) . '/' . self::directory($key) . '.php';
    }

    public static function path(SiteKey $key): string
    {
        return constant('WP_PLUGIN_DIR') . '/' . self::directory($key);
    }

    public static function file(SiteKey $key): string
    {
        return constant('WP_PLUGIN_DIR') . '/' . self::basename($key);
    }

    public static function configPath(SiteKey $key): string
    {
        return self::path($key) . '/config';
    }

    /**
     * Whether the site has a site plugin, which then applies to it.
     */
    public static function exists(SiteKey $key): bool
    {
        return is_file(self::file($key));
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
     * The key of a blog, read straight from blogmeta.
     *
     * Usable before Acorn boots (from an mu-plugin), unlike
     * {@see SiteRepository}.
     */
    public static function keyForBlog(int $blogId): ?SiteKey
    {
        $value = get_site_meta($blogId, SiteMeta::Key->value, true);

        return is_string($value) ? SiteKey::tryFrom($value) : null;
    }

    /**
     * Site plugins in the current blog's active plugin list, by basename.
     *
     * @return list<string>
     */
    public static function listedActive(): array
    {
        $active = get_option('active_plugins', []);

        return self::sitePlugins(is_array($active) ? $active : []);
    }

    /**
     * Site plugins that are network-activated, by basename.
     *
     * @return list<string>
     */
    public static function networkActivated(): array
    {
        $network = get_site_option('active_sitewide_plugins', []);

        return self::sitePlugins(is_array($network) ? array_keys($network) : []);
    }

    /**
     * @param array<array-key, mixed> $plugins
     *
     * @return list<string>
     */
    private static function sitePlugins(array $plugins): array
    {
        $sitePlugins = [];
        foreach ($plugins as $plugin) {
            if (is_string($plugin) && self::isSitePlugin($plugin)) {
                $sitePlugins[] = $plugin;
            }
        }

        return $sitePlugins;
    }
}
