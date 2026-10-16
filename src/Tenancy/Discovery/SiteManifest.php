<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Discovery;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
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
use function is_file;
use function is_string;
use function json_decode;
use function realpath;
use function rtrim;
use function var_export;

use const GLOB_ONLYDIR;
use const JSON_THROW_ON_ERROR;

/**
 * Discovers which providers, view composers and commands belong to which
 * site, theme and site plugin, with an optional cache.
 *
 * Discovery only lists class names; it never loads or registers anything.
 * Every class is autoloaded by Composer for every site: `app/Sites/*` through
 * the project's `App\` mapping, themes and site plugins through
 * `wikimedia/composer-merge-plugin`.  Loading a class has no side effects;
 * what is site-specific is registering providers, decided per request by
 * {@see \Kleinweb\Lib\Tenancy\SiteLoader}.
 *
 * @phpstan-type SiteCode array{providers: list<string>, composers: list<string>, commands: list<string>}
 * @phpstan-type Manifest array{sites: array<string, SiteCode>, packages: array<string, list<string>>}
 */
final class SiteManifest
{
    /** @var Manifest|null */
    private ?array $cached = null;

    private bool $cacheLoaded = false;

    /** @var array<string, SiteCode> */
    private array $sites = [];

    /** @var array<string, list<string>> */
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

    public function pluginPath(SiteKey $key): string
    {
        return \constant('WP_PLUGIN_DIR') . '/' . $key->value;
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
     * Providers declared by a theme or site plugin.
     *
     * Read from `extra.acorn.providers`, or `extra.laravel.providers`, in the
     * directory's `composer.json`, matching Acorn's own package discovery.
     *
     * @return list<string>
     */
    public function providers(string $directory): array
    {
        $directory = self::normalize($directory);

        $cached = $this->cache()['packages'][$directory] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        return $this->packages[$directory] ??= self::declaredProviders(
            $this->composerJson($directory) ?? [],
        );
    }

    /**
     * The parsed `composer.json` of a theme or site plugin, if it has one.
     *
     * @return array<array-key, mixed>|null
     */
    public function composerJson(string $directory): ?array
    {
        $path = self::normalize($directory) . '/composer.json';
        if (!is_file($path)) {
            return null;
        }

        try {
            $json = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($json) ? $json : null;
    }

    /**
     * Every theme and site-plugin directory that has a `composer.json`.
     *
     * @return list<string>
     */
    public function packageDirectories(SiteRepository $sites): array
    {
        $directories = glob(get_theme_root() . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key !== null) {
                $directories[] = $this->pluginPath($key);
            }
        }

        return array_values(array_filter(
            array_map(self::normalize(...), $directories),
            static fn (string $dir): bool => is_file("{$dir}/composer.json"),
        ));
    }

    /**
     * Discover everything and write the cache file.
     */
    public function write(SiteRepository $sites): string
    {
        $this->cached = null;
        $this->cacheLoaded = true;

        $manifest = ['sites' => [], 'packages' => []];

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key !== null && $key->isNamespaceable()) {
                $manifest['sites'][$key->value] = $this->discoverSite($key);
            }
        }

        foreach ($this->packageDirectories($sites) as $directory) {
            $manifest['packages'][$directory] = self::declaredProviders(
                $this->composerJson($directory) ?? [],
            );
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
     * @param array<array-key, mixed> $composer
     *
     * @return list<string>
     */
    private static function declaredProviders(array $composer): array
    {
        $extra = $composer['extra'] ?? [];
        $providers = $extra['acorn']['providers'] ?? $extra['laravel']['providers'] ?? [];

        return self::strings($providers);
    }

    private static function normalize(string $directory): string
    {
        return rtrim(realpath($directory) ?: $directory, '/');
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
            $this->cached = is_array($loaded) ? self::validate($loaded) : null;
        }

        return $this->cached ?? ['sites' => [], 'packages' => []];
    }

    /**
     * Validate a cache file's contents, dropping anything malformed.
     *
     * @param array<array-key, mixed> $loaded
     *
     * @return Manifest
     */
    private static function validate(array $loaded): array
    {
        $manifest = ['sites' => [], 'packages' => []];

        $sites = $loaded['sites'] ?? [];
        foreach (is_array($sites) ? $sites : [] as $key => $code) {
            if (is_string($key) && is_array($code)) {
                $manifest['sites'][$key] = [
                    'providers' => self::strings($code['providers'] ?? []),
                    'composers' => self::strings($code['composers'] ?? []),
                    'commands' => self::strings($code['commands'] ?? []),
                ];
            }
        }

        $packages = $loaded['packages'] ?? [];
        foreach (is_array($packages) ? $packages : [] as $directory => $providers) {
            if (is_string($directory)) {
                $manifest['packages'][$directory] = self::strings($providers);
            }
        }

        return $manifest;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
