<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Bootstrappers;

use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Kleinweb\Lib\Tenancy\Contracts\BlogBootstrapper;

use function is_array;
use function is_string;
use function method_exists;
use function rtrim;

/**
 * Keeps Laravel's cache separate per blog, including across `switch_to_blog()`.
 *
 * Acorn sets `cache.prefix` per blog on `switch_blog`, which is not enough:
 *
 * - the file store, Acorn's default, ignores prefixes entirely
 *   (`FileStore::getPrefix()` returns an empty string), so every blog shares
 *   one file cache;
 * - stores are built once and memoized by `CacheManager`, so a prefix
 *   changed after the first cache call never reaches them;
 * - the array store keeps values in memory with no prefix, so a console
 *   command looping over sites reads the previous site's values.
 *
 * This bootstrapper updates config for stores not yet built, and changes
 * already-built stores in place, so every holder of a cache repository (the
 * facade, injected instances, `cache.store`) follows the switch:
 *
 * - file stores use `{path}/blog_{id}`;
 * - stores with their own `prefix` get `{prefix}blog_{id}_`, others take
 *   Acorn's per-blog `cache.prefix`;
 * - array stores get one in-memory store per blog.
 *
 * Runs after Acorn's own `switch_blog` handler, so `cache.prefix` is current.
 */
final class CacheBootstrapper implements BlogBootstrapper
{
    /** @var array<string, string>|null store => file path before isolation */
    private ?array $basePaths = null;

    /** @var array<string, string> store => store prefix before isolation */
    private array $basePrefixes = [];

    /** @var array<string, array<int, ArrayStore>> store => blog ID => store */
    private array $arrays = [];

    /** Blog the previous call applied, i.e. the blog stores were built under. */
    private ?int $activeBlogId = null;

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {}

    public function apply(int $blogId): void
    {
        $this->capture();

        foreach ($this->basePaths ?? [] as $name => $path) {
            $this->config->set("cache.stores.{$name}.path", "{$path}/blog_{$blogId}");
        }

        foreach ($this->basePrefixes as $name => $prefix) {
            $this->config->set("cache.stores.{$name}.prefix", "{$prefix}blog_{$blogId}_");
        }

        $previousBlogId = $this->activeBlogId ?? $blogId;
        $this->activeBlogId = $blogId;

        if (!$this->container->resolved('cache')) {
            return;
        }

        $manager = $this->container->make('cache');
        if (!$manager instanceof CacheManager) {
            return;
        }

        foreach (self::resolvedStores($manager) as $name => $repository) {
            if ($repository instanceof CacheRepository) {
                $this->isolate((string) $name, $repository, $blogId, $previousBlogId);
            }
        }
    }

    private function isolate(
        string $name,
        CacheRepository $repository,
        int $blogId,
        int $previousBlogId,
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
            // A store not seen before was built under the previous blog.
            if (!isset($this->arrays[$name])) {
                $this->arrays[$name] = [$previousBlogId => $store];
            }

            $current = $this->arrays[$name][$blogId] ??= new ArrayStore(
                (bool) $this->config->get("cache.stores.{$name}.serialize", false),
            );

            if ($current !== $store) {
                $repository->setStore($current);
            }

            return;
        }

        if (!method_exists($store, 'setPrefix')) {
            return;
        }

        $prefix = $this->config->get("cache.stores.{$name}.prefix")
            ?? $this->config->get('cache.prefix');
        if (is_string($prefix)) {
            $store->setPrefix($prefix);
        }
    }

    /**
     * Record the configured file paths and store prefixes once, before any
     * per-blog suffix is added.
     */
    private function capture(): void
    {
        if ($this->basePaths !== null) {
            return;
        }

        $this->basePaths = [];
        $stores = $this->config->get('cache.stores', []);

        foreach (is_array($stores) ? $stores : [] as $name => $store) {
            if (!is_string($name) || !is_array($store)) {
                continue;
            }

            if (($store['driver'] ?? null) === 'file' && is_string($store['path'] ?? null)) {
                $this->basePaths[$name] = rtrim($store['path'], '/');
            }

            if (is_string($store['prefix'] ?? null) && $store['prefix'] !== '') {
                $this->basePrefixes[$name] = $store['prefix'];
            }
        }
    }

    /**
     * Stores the manager has already built, keyed by name.
     *
     * `CacheManager` keeps these in a protected property with no accessor.
     *
     * @return array<array-key, mixed>
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
