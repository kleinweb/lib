<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Discovery;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Roots\Acorn\View\Composer;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function realpath;
use function rtrim;
use function var_export;

use const GLOB_ONLYDIR;

/**
 * Discovers site, site-plugin and theme code, with an optional cache.
 *
 * Discovery does not depend on which site is active, so one cache serves the
 * whole network.  Activation (is this plugin active here? which theme?) is
 * decided at runtime by {@see \Kleinweb\Lib\Tenancy\SiteLoader}.
 *
 * @phpstan-type SiteCode array{providers: list<string>, composers: list<string>, commands: list<string>}
 * @phpstan-type PackageCode array{psr4: array<string, list<string>>, providers: list<string>}
 * @phpstan-type Manifest array{sites: array<string, SiteCode>, packages: array<string, PackageCode>}
 */
final class SiteManifest
{
    /** @var Manifest|null */
    private ?array $cached = null;

    private bool $cacheLoaded = false;

    /** @var array<string, SiteCode> */
    private array $sites = [];

    /** @var array<string, PackageCode> */
    private array $packages = [];

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {}

    public function cachePath(): string
    {
        return $this->app->bootstrapPath('cache/sites.php');
    }

    /**
     * Root namespace for a site's application code, e.g. `App\Sites\NewsHub\`.
     */
    public function siteNamespace(SiteKey $key): string
    {
        return $this->app->getNamespace() . 'Sites\\' . $key->studly() . '\\';
    }

    /**
     * Directory for a site's application code, e.g. `app/Sites/NewsHub`.
     */
    public function sitePath(SiteKey $key): string
    {
        return $this->app->path('Sites/' . $key->studly());
    }

    /**
     * Providers, view composers and commands under `app/Sites/{Key}`.
     *
     * @return SiteCode
     */
    public function site(SiteKey $key): array
    {
        $cached = $this->cache()['sites'][$key->value] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        return $this->sites[$key->value] ??= $this->discoverSite($key);
    }

    /**
     * Autoload map and providers for a theme or site-plugin directory.
     *
     * Read from the directory's `composer.json`: `autoload.psr-4`, and
     * providers from `extra.acorn.providers` or `extra.laravel.providers`.  A
     * site plugin without a `composer.json` maps `src/` to the site namespace
     * and has its providers discovered under `src/Providers`.
     *
     * @return PackageCode
     */
    public function package(string $directory, ?SiteKey $siteKey = null): array
    {
        $directory = rtrim(realpath($directory) ?: $directory, '/');

        $cached = $this->cache()['packages'][$directory] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        return $this->packages[$directory] ??= $this->discoverPackage($directory, $siteKey);
    }

    /**
     * Discover everything and write the cache file.
     *
     * Covers every keyed site, each site's plugin directory, and every theme
     * with a `composer.json`.
     */
    public function write(SiteRepository $sites): string
    {
        $this->cached = null;
        $this->cacheLoaded = true;

        $manifest = ['sites' => [], 'packages' => []];

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key === null || !$key->isNamespaceable()) {
                continue;
            }

            $manifest['sites'][$key->value] = $this->discoverSite($key);

            $plugin = $this->pluginPath($key);
            if (!is_dir($plugin)) {
                continue;
            }

            $real = rtrim(realpath($plugin) ?: $plugin, '/');
            $manifest['packages'][$real] = $this->discoverPackage($real, $key);
        }

        foreach ($this->themeDirectories() as $theme) {
            $manifest['packages'][$theme] = $this->discoverPackage($theme, null);
        }

        $path = $this->cachePath();
        $this->files->ensureDirectoryExists($this->files->dirname($path));
        $this->files->replace($path, '<?php return ' . var_export($manifest, true) . ';' . "\n");

        return $path;
    }

    public function clear(): bool
    {
        $this->cached = null;
        $this->cacheLoaded = false;

        return !is_file($this->cachePath()) || $this->files->delete($this->cachePath());
    }

    public function pluginPath(SiteKey $key): string
    {
        return \constant('WP_PLUGIN_DIR') . '/' . $key->value;
    }

    /**
     * @return SiteCode
     */
    private function discoverSite(SiteKey $key): array
    {
        if (!$key->isNamespaceable()) {
            return ['providers' => [], 'composers' => [], 'commands' => []];
        }

        $path = $this->sitePath($key);
        $namespace = $this->siteNamespace($key);

        return [
            'providers' => ClassFinder::find(
                "{$path}/Providers",
                "{$namespace}Providers\\",
                ServiceProvider::class,
            ),
            'composers' => ClassFinder::find(
                "{$path}/View/Composers",
                "{$namespace}View\\Composers\\",
                Composer::class,
            ),
            'commands' => ClassFinder::find(
                "{$path}/Console/Commands",
                "{$namespace}Console\\Commands\\",
                Command::class,
            ),
        ];
    }

    /**
     * @return PackageCode
     */
    private function discoverPackage(string $directory, ?SiteKey $siteKey): array
    {
        $composer = $this->readComposerJson("{$directory}/composer.json");

        if ($composer !== null) {
            return [
                'psr4' => self::psr4($composer, $directory),
                'providers' => self::providers($composer),
            ];
        }

        if ($siteKey === null || !$siteKey->isNamespaceable()) {
            return ['psr4' => [], 'providers' => []];
        }

        // Site plugin without composer.json: src/ shares the site namespace.
        $namespace = $this->siteNamespace($siteKey);
        $psr4 = [$namespace => ["{$directory}/src"]];
        Psr4Autoloader::register($psr4);

        return [
            'psr4' => $psr4,
            'providers' => ClassFinder::find(
                "{$directory}/src/Providers",
                "{$namespace}Providers\\",
                ServiceProvider::class,
            ),
        ];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function readComposerJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        try {
            $json = json_decode($this->files->get($path), true, flags: \JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($json) ? $json : null;
    }

    /**
     * @param array<array-key, mixed> $composer
     *
     * @return array<string, list<string>>
     */
    private static function psr4(array $composer, string $directory): array
    {
        $map = $composer['autoload']['psr-4'] ?? [];
        if (!is_array($map)) {
            return [];
        }

        $psr4 = [];
        foreach ($map as $prefix => $paths) {
            if (!is_string($prefix)) {
                continue;
            }

            $paths = array_filter((array) $paths, is_string(...));
            $psr4[$prefix] = array_values(array_map(
                static fn (string $path): string => rtrim("{$directory}/{$path}", '/'),
                $paths,
            ));
        }

        return $psr4;
    }

    /**
     * The first PSR-4 prefix a `composer.json` declares, if any.
     */
    public function rootNamespace(string $directory): ?string
    {
        $prefixes = \array_keys($this->package($directory)['psr4']);

        return $prefixes[0] ?? null;
    }

    /**
     * @param array<array-key, mixed> $composer
     *
     * @return list<string>
     */
    private static function providers(array $composer): array
    {
        $extra = $composer['extra'] ?? [];
        $providers = $extra['acorn']['providers'] ?? $extra['laravel']['providers'] ?? [];

        return is_array($providers) ? array_values(array_filter($providers, is_string(...))) : [];
    }

    /**
     * Theme directories that carry a `composer.json`.
     *
     * @return list<string>
     */
    private function themeDirectories(): array
    {
        $themes = glob(get_theme_root() . '/*', GLOB_ONLYDIR) ?: [];

        return array_values(array_filter(
            array_map(
                static fn (string $dir): string => rtrim(realpath($dir) ?: $dir, '/'),
                $themes,
            ),
            static fn (string $dir): bool => is_file("{$dir}/composer.json"),
        ));
    }

    /**
     * Validate a cache file's contents, dropping anything malformed.
     *
     * @param array<array-key, mixed> $loaded
     *
     * @return Manifest
     */
    private static function normalize(array $loaded): array
    {
        $manifest = ['sites' => [], 'packages' => []];

        foreach (self::arrayAt($loaded, 'sites') as $key => $code) {
            if (!is_string($key) || !is_array($code)) {
                continue;
            }

            $manifest['sites'][$key] = [
                'providers' => self::strings($code['providers'] ?? []),
                'composers' => self::strings($code['composers'] ?? []),
                'commands' => self::strings($code['commands'] ?? []),
            ];
        }

        foreach (self::arrayAt($loaded, 'packages') as $directory => $package) {
            if (!is_string($directory) || !is_array($package)) {
                continue;
            }

            $psr4 = [];
            foreach (self::arrayAt($package, 'psr4') as $prefix => $paths) {
                if (is_string($prefix)) {
                    $psr4[$prefix] = self::strings($paths);
                }
            }

            $manifest['packages'][$directory] = [
                'psr4' => $psr4,
                'providers' => self::strings($package['providers'] ?? []),
            ];
        }

        return $manifest;
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    private static function arrayAt(array $array, string $key): array
    {
        return is_array($array[$key] ?? null) ? $array[$key] : [];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }

    /**
     * @return Manifest
     */
    private function cache(): array
    {
        if (!$this->cacheLoaded) {
            $this->cacheLoaded = true;
            $path = $this->cachePath();
            $loaded = is_file($path) ? require $path : null;
            $this->cached = is_array($loaded) ? self::normalize($loaded) : null;
        }

        return $this->cached ?? ['sites' => [], 'packages' => []];
    }
}
