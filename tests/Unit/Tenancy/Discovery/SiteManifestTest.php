<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy\Discovery;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Sites\News\Providers\NewsServiceProvider;

use function array_keys;
use function sys_get_temp_dir;
use function uniqid;

final class SiteManifestTest extends TestCase
{
    private string $base;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main');
        WordPress::addSite(2, 'news');
        WordPress::addSite(5, 'events');

        $this->base = sys_get_temp_dir() . '/' . uniqid('kleinweb-manifest-', true);
        $this->files = new Filesystem();
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->base);
    }

    public function testReadsProvidersFromComposerJson(): void
    {
        $manifest = $this->manifest();

        self::assertSame(
            [NewsServiceProvider::class],
            $manifest->providers(SitePlugin::path(SiteKey::from('news'))),
        );
        self::assertSame([], $manifest->providers(SitePlugin::path(SiteKey::from('events'))));
    }

    public function testReadsASitePluginsConfigByFileName(): void
    {
        $config = $this->manifest()->config(SiteKey::from('events'));

        self::assertSame(['post-types', 'services'], array_keys($config));
        self::assertSame([], $this->manifest()->config(SiteKey::from('main')));
    }

    public function testTheCacheRoundTrips(): void
    {
        $manifest = $this->manifest();
        $path = $manifest->write(new SiteRepository());
        self::assertFileExists($path);
        $written = require $path;
        self::assertIsArray($written);
        self::assertArrayHasKey('news', $written['config']);
        self::assertArrayHasKey(SitePlugin::path(SiteKey::from('news')), $written['packages']);

        $cached = $this->manifest();
        self::assertSame(
            $manifest->config(SiteKey::from('news')),
            $cached->config(SiteKey::from('news')),
        );
        self::assertSame(
            [NewsServiceProvider::class],
            $cached->providers(SitePlugin::path(SiteKey::from('news'))),
        );

        self::assertTrue($cached->clear());
        self::assertFileDoesNotExist($path);
    }

    private function manifest(): SiteManifest
    {
        return new SiteManifest(new Application($this->base), $this->files);
    }
}
