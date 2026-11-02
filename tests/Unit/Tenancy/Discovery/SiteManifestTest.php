<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Sites\News\Providers\NewsServiceProvider;

use function Kleinweb\Lib\Tests\tempPath;

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(5, 'events');

    $this->base = tempPath('manifest');
    $this->manifest = fn (): SiteManifest => new SiteManifest(
        new Application($this->base),
        new Filesystem(),
    );
    $this->newsPlugin = SitePlugin::path(SiteKey::from('news'));
});

afterEach(function (): void {
    (new Filesystem())->deleteDirectory($this->base);
});

it('reads providers from composer.json', function (): void {
    expect(($this->manifest)()->providers($this->newsPlugin))->toBe([NewsServiceProvider::class])
        ->and(($this->manifest)()->providers(SitePlugin::path(SiteKey::from('events'))))->toBe([])
    ;
});

it("reads a site plugin's config by file name", function (): void {
    expect(array_keys(($this->manifest)()->config(SiteKey::from('events'))))
        ->toBe(['post-types', 'services'])
        ->and(($this->manifest)()->config(SiteKey::from('main')))->toBe([])
    ;
});

it('caches and clears what it reads', function (): void {
    $manifest = ($this->manifest)();
    $path = $manifest->write(new SiteRepository());

    $written = require $path;
    expect($path)->toBeFile()
        ->and($written['config'])->toHaveKey('news')
        ->and($written['packages'])->toHaveKey($this->newsPlugin)
    ;

    $cached = ($this->manifest)();
    expect($cached->config(SiteKey::from('news')))->toBe($manifest->config(SiteKey::from('news')))
        ->and($cached->providers($this->newsPlugin))->toBe([NewsServiceProvider::class])
        ->and($cached->clear())->toBeTrue()
        ->and($path)->not->toBeFile()
    ;
});
