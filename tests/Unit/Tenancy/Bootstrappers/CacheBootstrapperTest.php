<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy\Bootstrappers;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Tenancy\Bootstrappers\CacheBootstrapper;
use Kleinweb\Lib\Tests\Support\PrefixedStore;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function uniqid;

final class CacheBootstrapperTest extends TestCase
{
    private string $dir;

    private Config $config;

    private CacheManager $cache;

    private CacheBootstrapper $bootstrapper;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/' . uniqid('kleinweb-cache-', true);

        $app = new Application(sys_get_temp_dir());
        $this->config = new Config(['cache' => [
            'default' => 'file',
            'prefix' => 'app-cache-blog_1_',
            'stores' => [
                'file' => ['driver' => 'file', 'path' => "{$this->dir}/data"],
                'array' => ['driver' => 'array', 'serialize' => false],
                'shared' => ['driver' => 'prefixed'],
                'own' => ['driver' => 'prefixed', 'prefix' => 'own-'],
            ],
        ]]);
        $app->instance('config', $this->config);
        $app->instance('files', new Filesystem());
        $this->cache = new CacheManager($app);
        $app->instance('cache', $this->cache);

        $this->cache->extend('prefixed', self::prefixedStore(...));

        $this->bootstrapper = new CacheBootstrapper($app, $this->config);
        $this->bootstrapper->apply(1);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->dir);
    }

    public function testFileAndArrayStoresAreIsolatedPerBlog(): void
    {
        $this->cache->store('file')->put('k', 'blog 1', 60);
        $this->cache->store('array')->put('k', 'blog 1', 60);
        self::assertSame("{$this->dir}/data/blog_1", $this->config->get('cache.stores.file.path'));

        $this->switchTo(2);
        self::assertNull($this->cache->store('file')->get('k'));
        self::assertNull($this->cache->store('array')->get('k'));
        $this->cache->store('file')->put('k', 'blog 2', 60);

        $this->switchTo(1);
        self::assertSame('blog 1', $this->cache->store('file')->get('k'));
        self::assertSame('blog 1', $this->cache->store('array')->get('k'));
    }

    public function testRepositoriesResolvedEarlierFollowTheSwitch(): void
    {
        $injected = $this->cache->store('array');
        $injected->put('k', 'blog 1', 60);

        $this->switchTo(2);
        self::assertNull($injected->get('k'));

        $this->switchTo(1);
        self::assertSame('blog 1', $injected->get('k'));
    }

    public function testPrefixedStoresFollowTheSwitch(): void
    {
        $this->switchTo(2);
        self::assertSame('app-cache-blog_2_', $this->prefix('shared'));
        self::assertSame('own-blog_2_', $this->prefix('own'));

        $this->switchTo(1);
        $this->bootstrapper->apply(1);
        self::assertSame('app-cache-blog_1_', $this->prefix('shared'));
        self::assertSame('own-blog_1_', $this->prefix('own'));
    }

    /**
     * As on `switch_blog`: Acorn sets `cache.prefix` first, then the
     * bootstrapper runs.
     */
    private function switchTo(int $blogId): void
    {
        $this->config->set('cache.prefix', "app-cache-blog_{$blogId}_");
        $this->bootstrapper->apply($blogId);
    }

    private function prefix(string $store): string
    {
        $store = $this->cache->store($store)->getStore();
        self::assertInstanceOf(PrefixedStore::class, $store);

        return $store->getPrefix();
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function prefixedStore(Container $app, array $config): CacheRepository
    {
        $store = new PrefixedStore();
        $prefix = $config['prefix'] ?? $app->make('config')->get('cache.prefix');
        $store->setPrefix(\is_string($prefix) ? $prefix : '');

        return new CacheRepository($store, $config);
    }
}
