<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Tenancy\Bootstrappers\CacheBootstrapper;
use Kleinweb\Lib\Tests\Support\PrefixedStore;

use function Kleinweb\Lib\Tests\tempPath;

/**
 * A store that keeps a prefix, standing in for Redis or Memcached.
 *
 * @param array<string, mixed> $config
 */
function prefixedStore(Container $app, array $config): CacheRepository
{
    $store = new PrefixedStore();
    $prefix = $config['prefix'] ?? $app->make('config')->get('cache.prefix');
    $store->setPrefix(is_string($prefix) ? $prefix : '');

    return new CacheRepository($store, $config);
}

beforeEach(function (): void {
    $this->dir = tempPath('cache');

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

    $this->cache->extend('prefixed', prefixedStore(...));

    $this->bootstrapper = new CacheBootstrapper($app, $this->config);
    $this->bootstrapper->apply(1);

    // As on switch_blog: Acorn sets `cache.prefix` first, then the
    // bootstrapper runs.
    $this->switchTo = function (int $blogId): void {
        $this->config->set('cache.prefix', "app-cache-blog_{$blogId}_");
        $this->bootstrapper->apply($blogId);
    };

    $this->prefix = fn (string $store): string => $this->cache
        ->store($store)
        ->getStore()
        ->getPrefix()
    ;
});

afterEach(function (): void {
    (new Filesystem())->deleteDirectory($this->dir);
});

it('isolates file and array stores per blog', function (): void {
    $this->cache->store('file')->put('k', 'blog 1', 60);
    $this->cache->store('array')->put('k', 'blog 1', 60);
    expect($this->config->get('cache.stores.file.path'))->toBe("{$this->dir}/data/blog_1");

    ($this->switchTo)(2);
    expect($this->cache->store('file')->get('k'))->toBeNull()
        ->and($this->cache->store('array')->get('k'))->toBeNull()
    ;
    $this->cache->store('file')->put('k', 'blog 2', 60);

    ($this->switchTo)(1);
    expect($this->cache->store('file')->get('k'))->toBe('blog 1')
        ->and($this->cache->store('array')->get('k'))->toBe('blog 1')
    ;
});

it('moves repositories resolved earlier along with the switch', function (): void {
    $injected = $this->cache->store('array');
    $injected->put('k', 'blog 1', 60);

    ($this->switchTo)(2);
    expect($injected->get('k'))->toBeNull();

    ($this->switchTo)(1);
    expect($injected->get('k'))->toBe('blog 1');
});

it('re-prefixes prefixed stores on every switch, without piling up', function (): void {
    ($this->switchTo)(2);
    expect(($this->prefix)('shared'))->toBe('app-cache-blog_2_')
        ->and(($this->prefix)('own'))->toBe('own-blog_2_')
    ;

    ($this->switchTo)(1);
    $this->bootstrapper->apply(1);
    expect(($this->prefix)('shared'))->toBe('app-cache-blog_1_')
        ->and(($this->prefix)('own'))->toBe('own-blog_1_')
    ;
});
