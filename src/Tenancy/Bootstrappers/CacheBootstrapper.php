<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Bootstrappers;

use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\FileStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Kleinweb\Lib\Tenancy\Contracts\SiteBootstrapper;

// use Illuminate\Cache\Repository as CacheRepository;

/**
 * Isolate Acorn cache per site context.
 *
 * Acorn sets `cache.prefix` per site on `switch_blog`,
 * but this does not result in proper cache isolation across sites.
 *
 * This bootstrapper should run after Acorn's `switch_blog` event handler
 * so that `cache.prefix` is current.
 *
 * TODO: confirm slop-generated diagnosis of possible upstream bug
 */
final class CacheBootstrapper implements SiteBootstrapper
{
    /** @var array<string, string>|null store => pre-isolation file path */
    private ?array $basePaths = null;

    /** @var array<string, string> store => pre-isolation store prefix */
    private array $basePrefixes = [];

    /** @var ArrayStore store prefix => site ID => store */
    private array $arrays = [];

    private ?int $activeSiteId = null;

    public function __construct(
        private readonly Container $container,
        private readonly ConfigRepository $config,
    ) {}

    public function apply(int $siteId): void
    {
        $this->capture();

        foreach ($this->basePaths ?? [] as $name => $path) {
            $this->config->set("cache.stores.{$name}.path", "{$path}/site_{$siteId}");
        }

        foreach ($this->basePrefixes as $name => $prefix) {
            $this->config->set("cache.stores.{$name}.prefix", "{$prefix}site_{$siteId}_");
        }

        $prevSiteId = $this->activeSiteId ?? $siteId;

        $this->activeSiteId = $siteId;

        if (!$this->container->resolved('cache')) {
            return;
        }

        $manager = $this->container->make('cache');
        if (!$manager instanceof CacheManager) {
            return;
        }

        foreach (self::resolvedStores($manager) as $name => $repository) {
            if ($repository instanceof CacheRepository) {
                $this->isolate((string) $name, $repository, $siteId, $prevSiteId);
            }
        }
    }

    private function isolate(
        string $name,
        CacheRepository $repository,
        int $siteId,
        int $prevSiteId,
    ): void {
        $store = $repository->getStore();

        if ($store instanceof FileStore) {
            $path = $this->config->get("cache.stores.{$name}.path");

            if (is_string($path) && isset($this->basePaths[$name])) {
                $store->setDirectory($path);
            }

            return;
        }

        if ($store instanceof ArrayStore) {
            // A newly-seen store is built in the previous site context.
            if (!isset($this->arrays[$name])) {
                $this->arrays[$name] = [$prevSiteId => $store];
            }

            $current = $this->arrays[$name][$siteId] ??= new ArrayStore(
                (bool) $this->config->get("cache.stores.{$name}.serialize", false),
            );

            if ($current !== $store) {
                // FIXME: no contract
                $repository->setStore($current);
            }

            return;
        }

        $prefix = $this->config->get("cache.stores.{$name}.prefix")
            ?? $this->config->get('cache.prefix');

        if (is_string($prefix)) {
            // FIXME: no contract
            $store->setPrefix($prefix);
        }
    }

    /**
     * Record the configured file paths and store prefixes prior to the addition of per-site suffixes.
     */
    private function capture(): void
    {
        if ($this->basePaths !== null) {
            return;
        }

        $this->basePaths = [];
        $stores = $this->config->get('cache.stores', []);

        foreach (is_array($stores) ? $stores : [] as $name => $store) {
            // TODO
        }
    }

    /**
     * Stores the manager has already built, keyed by name.
     *
     * @return array<string, mixed>
     */
    private static function resolvedStores(CacheManager $manager): array
    {
        $read = Closure::bind(
            static fn (CacheManager $manager): array => $manager->stores,
            null,
            CacheManager::class,
        );

        return $read($manager);
    }
}
