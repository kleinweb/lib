<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Kleinweb\Lib\Support\Plugin;

/**
 * FIXME: integrate with {@see Plugin}
 */
final class SitePlugin
{
    public const string PREFIX = 'kleinweb-site-';

    public static function directory(SiteKey $key): string
    {
        return self::PREFIX . $key->value;
    }

    public static function basename(SiteKey $key): string
    {
        // FIXME: if this is how this function is used (it is), then its name is incorrect.
        return self::directory($key) . '/' . self::directory($key);
    }

    public static function path(SiteKey $key): string
    {
        return constant('WP_PLUGIN_DIR') . '/' . self::directory($key) . '/' . self::directory($key);
    }

    public static function file(SiteKey $key): string
    {
        return constant('WP_PLUGIN_DIR') . '/' . self::basename($key);
    }

    public static function configPath(SiteKey $key): string
    {
        return self::path($key) . '/config';
    }

    public static function exists(SiteKey $key): bool
    {
        return is_file(self::file($key));
    }

    /**
     * Default root namespace for a site plugin.
     */
    public static function namespace(SiteKey $key): string
    {
        // FIXME: is this correct?
        return 'Kleinweb\\Sites\\' . $key->studly() . '\\';
    }

    public static function keyFor(string $dir): ?SiteKey
    {
        return str_starts_with($dir, self::PREFIX)
            ? SiteKey::tryFrom(substr($dir, strlen(self::PREFIX)))
            : null;
    }

    /**
     * Whether a plugin basename belongs to a site plugin.
     *
     * TODO: clarify naming/docstring?
     */
    public static function isSitePlugin(string $basename): bool
    {
        return str_starts_with($basename, self::PREFIX);
    }

    /**
     * The site key for a given site's ID.
     *
     * Usable before Acorn boots from a mu-plugin.
     */
    public static function keyForSite(int $siteId): ?SiteKey
    {
        $value = get_site_meta($siteId, SiteMeta::Key->value, true);

        return is_string($value) ? SiteKey::tryFrom($value) : null;
    }

    /**
     * Site plugins in the current blog's active plugin list, by basename.
     *
     * @return string[]
     */
    public static function active(): array
    {
        $active = get_option('active_plugins', []);

        return self::sitePlugins(is_array($active) ? $active : []);
    }

    /**
     * Site plugins that are network-activated, by basename.
     *
     * @return string[]
     */
    public static function networkActive(): array
    {
        $network = get_site_option('active_sitewide_plugins', []);

        return self::sitePlugins(is_array($network) ? array_keys($network) : []);
    }

    /**
     * @param array<string, mixed> $plugins
     *
     * @return string[]
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
