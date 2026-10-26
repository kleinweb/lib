<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Discovery;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Symfony\Component\Finder\Finder;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_scalar;
use function is_string;
use function json_decode;
use function realpath;
use function rtrim;
use function str_replace;
use function substr;
use function var_export;

use const GLOB_ONLYDIR;
use const JSON_THROW_ON_ERROR;

/**
 * Reads what site plugins and themes declare, with an optional cache.
 *
 * - Providers: `extra.acorn.providers`, or `extra.laravel.providers`, in a
 *   site plugin's or theme's `composer.json`, matching Acorn's own package
 *   discovery.
 * - Config: a site plugin's `config/*.php`.
 *
 * Nothing here loads or registers anything, and nothing depends on which
 * site is active, so one cache (`site:cache`) serves the whole network.
 * Which declarations apply to a request is decided by
 * {@see \Kleinweb\Lib\Tenancy\SiteLoader} and
 * {@see \Kleinweb\Lib\Tenancy\Bootstrappers\ConfigBootstrapper}.
 *
 * @phpstan-type SiteConfig array<string, array<array-key, mixed>>
 * @phpstan-type Manifest array{packages: array<string, list<string>>, config: array<string, SiteConfig>}
 */
final class SiteManifest
{
    /** @var Manifest|null */
    private ?array $cached = null;

    private bool $cacheLoaded = false;

    /** @var array<string, list<string>> */
    private array $packages = [];

    /** @var array<string, SiteConfig> */
    private array $config = [];

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {}

    public function cachePath(): string
    {
        return $this->app->bootstrapPath('cache/sites.php');
    }

    /**
     * Providers declared by a site plugin or theme directory.
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
     * Config overlays from a site plugin's `config/` directory, by name.
     *
     * `config/mail.php` overlays `mail`; a file in a subdirectory is named
     * the way Laravel names nested config, `config/services/stripe.php`
     * becoming `services.stripe`.
     *
     * @return SiteConfig
     */
    public function config(SiteKey $key): array
    {
        $cached = $this->cache()['config'][$key->value] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        return $this->config[$key->value] ??= self::readConfig(SitePlugin::configPath($key));
    }

    /**
     * The parsed `composer.json` of a site plugin or theme, if it has one.
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
     * Every theme, and every site plugin of a keyed site, with a
     * `composer.json`.
     *
     * @return list<string>
     */
    public function packageDirectories(SiteRepository $sites): array
    {
        $directories = glob(get_theme_root() . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key !== null) {
                $directories[] = SitePlugin::path($key);
            }
        }

        return array_values(array_filter(
            array_map(self::normalize(...), $directories),
            static fn (string $dir): bool => is_file("{$dir}/composer.json"),
        ));
    }

    /**
     * Read every declaration and write the cache file.
     */
    public function write(SiteRepository $sites): string
    {
        $this->cached = null;
        $this->cacheLoaded = true;

        $manifest = ['packages' => [], 'config' => []];

        foreach ($this->packageDirectories($sites) as $directory) {
            $manifest['packages'][$directory] = self::declaredProviders(
                $this->composerJson($directory) ?? [],
            );
        }

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key === null) {
                continue;
            }

            $config = self::readConfig(SitePlugin::configPath($key));
            if ($config !== [] && self::exportable($config)) {
                $manifest['config'][$key->value] = $config;
            }
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
     * @return SiteConfig
     */
    private static function readConfig(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $config = [];
        $finder = Finder::create()->files()->name('*.php')->in($directory)->sortByName();

        foreach ($finder as $file) {
            $name = str_replace('/', '.', substr($file->getRelativePathname(), 0, -4));
            $values = require $file->getPathname();

            if (is_array($values)) {
                $config[$name] = $values;
            }
        }

        return $config;
    }

    /**
     * Whether a value survives `var_export()`, so it can go in the cache file.
     */
    private static function exportable(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::exportable($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
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

        return $this->cached ?? ['packages' => [], 'config' => []];
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
        $manifest = ['packages' => [], 'config' => []];

        $packages = $loaded['packages'] ?? [];
        foreach (is_array($packages) ? $packages : [] as $directory => $providers) {
            if (is_string($directory)) {
                $manifest['packages'][$directory] = self::strings($providers);
            }
        }

        $config = $loaded['config'] ?? [];
        foreach (is_array($config) ? $config : [] as $key => $files) {
            if (!is_string($key) || !is_array($files)) {
                continue;
            }

            foreach ($files as $name => $values) {
                if (is_string($name) && is_array($values)) {
                    $manifest['config'][$key][$name] = $values;
                }
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
