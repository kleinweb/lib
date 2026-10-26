<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Discovery;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Kleinweb\Lib\Support\ServiceProvider;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Roots\Acorn\Application;
use Roots\Acorn\View\Composer;
use Throwable;

/**
 * Discover mapping of providers, view composers, and commands etc. to sites, themes, and the site mu-plugin.
 *
 * Supports an optional cache.
 *
 * While classes are autoloaded by Composer regardless of site scope, classes are not registered or booted automatically.
 *
 * Class mappings are derived from the `app/Sites/*` -> `App\Sites\` PSR-4 namespace correspondance.  Themes and site-specific plugins are supported by way of `wikimedia/composer-merge-plugin`.  Provider registration should be handled by {@see \Kleinweb\Lib\Tenancy\SiteLoader}.
 *
 * @phpstan-type SiteCode array{
 *     commands: string[],
 *     composers: string[],
 *     providers: string[],
 * }
 * @phpstan-type Manifest array{
 *     packages: array<string, string[]>,
 *     sites: array<string, SiteCode>,
 * }
 */
final class SiteManifest
{
    /** @var Manifest|null */
    private ?array $cached = null;

    private bool $cacheLoaded = false;

    /** @var array<string, SiteCode> */
    private array $sites = [];

    /** @var array<string, string[]> */
    private array $packages = [];

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $fs,
    ) {}

    public function cachePath(): string
    {
        return $this->app->bootstrapPath('cache/sites.php');
    }

    /**
     * Root namespace for a site's application code, e.g. `App\Sites\NewsHub\`
     */
    public function siteNamespace(SiteKey $key): string
    {
        return $this->app->getNamespace() . 'Sites\\' . $key->studly() . '\\';
    }

    /**
     * Base directory for a site's application code relative to the Acorn directory.
     */
    public function sitePath(SiteKey $key): string
    {
        return $this->app->path('Sites/' . $key->studly());
    }

    /**
     * Path to a site-specific plugin directory.
     */
    public function pluginPath(SiteKey $key): string
    {
        return constant('WP_PLUGIN_DIR') . '/' . $key->value;
    }

    /**
     * Providers, view composers, and commands under {@see SiteManifest::sitePath()}.
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
     * Providers declared by a theme or site-specific plugin.
     *
     * Following Acorn's own package discovery method, providers may be read from a package's `composer.json` by way of its `extra.acorn.providers` or `extra.laravel.providers` JSON properties.
     *
     * @return string[]
     */
    public function providers(string $dir): array
    {
        $dir = self::normalize($dir);
        $cached = $this->cache()['packages'][$dir] ?? null;

        if ($cached !== null) {
            return $cached;
        }

        return $this->packages[$dir] ??= self::declaredProviders(
            $this->composerJson($dir) ?? [],
        );
    }

    /**
     * Parsed `composer.json` from a theme or site-specific plugin, if existing.
     *
     * @return array<array-key, mixed>|null
     */
    public function composerJson(string $dir): ?array
    {
        $path = self::normalize($dir) . '/composer.json';

        if (!is_file($path)) {
            return null;
        }

        try {
            $json = json_decode($this->fs->get($path), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($json) ? $json : null;
    }

    /**
     * Every theme and site-specific plugin directory containing a `composer.json`.
     *
     * @return string[]
     *
     * @throws \Kleinweb\Lib\Tenancy\Exceptions\MultisiteOnly
     */
    public function packageDirs(SiteRepository $sites): array
    {
        $dirs = glob(get_theme_root() . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key !== null) {
                $dirs[] = $this->pluginPath($key);
            }
        }

        $hasComposerJson = static fn (string $dir): bool => is_file("{$dir}/composer.json");

        return collect($dirs)
            ->map(self::normalize(...))
            ->filter($hasComposerJson)
            ->values()
        ;
    }

    public function write(SiteRepository $sites): string
    {
        // TODO: should these really be set before doing anything?
        $this->cached = null;
        $this->cacheLoaded = true;

        // FIXME: if this is a "manifest", distinguish it from `::defaultSiteManifest()`
        $manifest = self::defaultSiteCodeMapping();

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key !== null && $key->isNamespaceable()) {
                $manifest['sites'][$key->value] = $this->discoverSite($key);
            }
        }

        foreach ($this->packageDirs($sites) as $dir) {
            $manifest['packages'][$dir] = self::declaredProviders(
                $this->composerJson($dir) ?? [],
            );
        }

        $path = $this->cachePath();
        $content = '<?php return ' . var_export($manifest, true) . ';' . "\n";

        $this->fs->ensureDirectoryExists($this->fs->dirname($path));
        $this->fs->replace($path, $content);

        return $path;
    }

    public function clear(): bool
    {
        $this->cached = null;
        $this->cacheLoaded = false;

        return !is_file($this->cachePath() || $this->fs->delete($this->cachePath()));
    }

    /**
     * @return SiteCode
     */
    private function discoverSite(SiteKey $key): array
    {
        $ret = self::defaultSiteCodeMapping();

        if (!$key->isNamespaceable()) {
            return $ret;
        }

        $path = $this->sitePath($key);
        $ns = Str::chopEnd($path, '\\');

        $ret['providers'][] = ClassFinder::find(
            "{$path}/Providers",
            "{$ns}\\Providers\\",
            ServiceProvider::class,
        );
        $ret['composers'][] = ClassFinder::find(
            "{$path}/View/Composers",
            "{$ns}\\View\\Composers\\",
            Composer::class,
        );
        $ret['commands'][] = ClassFinder::find(
            "{$path}/Console/Commands",
            "{$ns}\\Console\\Commands\\",
            Command::class,
        );

        return $ret;
    }

    /**
     * @param array<array-key, mixed> $composer
     *
     * @return string[]
     */
    private static function declaredProviders(array $composer): array
    {
        $extra = $composer['extra'] ?? [];
        $providers = $extra['acorn']['providers'] ?? $extra['laravel']['acorn']['providers'] ?? [];

        return self::strings($providers);
    }

    private static function normalizeDir(string $dir): string
    {
        return rtrim(realpath($dir) ?: $dir, '/');
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

        return $this->cached ?? self::defaultManifest();
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
        $manifest = self::defaultManifest();

        $sites = $loaded['sites'] ?? [];
        foreach (is_array($sites) ? $sites : [] as $key => $code) {
            if (is_string($key) && is_array($code)) {
                $manifest['sites'][$key] = [
                    'commands' => self::strings($code['commands'] ?? []),
                    'composers' => self::strings($code['composers'] ?? []),
                    'providers' => self::strings($code['providers'] ?? []),
                ];
            }
        }

        $packages = $loaded['packages'] ?? [];
        foreach (is_array($packages) ? $packages : [] as $dir => $providers) {
            if (is_string($dir)) {
                $manifest['packages'][$dir] = self::strings($providers);
            }
        }

        return $manifest;
    }

    /**
     * @return string[]
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }

    /**
     * @return Manifest
     */
    private static function defaultManifest(): array
    {
        return [
            'packages' => [],
            'sites' => [],
        ];
    }

    /**
     * @return SiteCode
     */
    private static function defaultSiteCodeMapping(): array
    {
        return [
            'commands' => [],
            'composers' => [],
            'providers' => [],
        ];
    }
}
