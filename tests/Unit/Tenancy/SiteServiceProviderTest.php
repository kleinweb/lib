<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * The news site plugin's provider against a real view factory.
 */

declare(strict_types=1);

use App\View\Composers\SiteHeader;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\ViewServiceProvider;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Sites\News\Providers\NewsServiceProvider;

use function Kleinweb\Lib\Tests\example;
use function Kleinweb\Lib\Tests\tempPath;

beforeEach(function (): void {
    WordPress::addSite(1, 'news');

    $this->base = tempPath('views');
    $this->app = new Application($this->base);
    Facade::setFacadeApplication($this->app);
    $this->app->instance('files', new Filesystem());
    $this->app->instance('config', new Config(['view' => [
        'paths' => [example('wp-content/mu-plugins/example/resources/views')],
        'compiled' => "{$this->base}/views",
    ]]));
    $this->app->register(ViewServiceProvider::class);

    $this->bootNews = function (): void {
        $this->app->register(NewsServiceProvider::class);
        $this->app->boot();
    };
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    (new Filesystem())->deleteDirectory($this->base);
});

it('overrides shared views even once the factory exists', function (): void {
    // As in Acorn: the factory is resolved (by the view provider attaching
    // composers) before site providers boot.
    $factory = $this->app->make('view');
    ($this->bootNews)();

    expect($factory->getFinder()->find('partials.masthead'))
        ->toContain('/kleinweb-site-news/resources/views/partials/masthead.blade.php')
    ;
});

it('replaces a shared composer with one that extends it', function (): void {
    $factory = $this->app->make('view');
    $factory->composer(SiteHeader::views(), SiteHeader::class);
    ($this->bootNews)();

    $view = $factory->make('partials.masthead');
    $factory->callComposer($view);

    expect($view->getData()['links'] ?? null)
        ->toBeArray()
        ->toHaveKey('Stories', message: 'the news composer ran')
        ->toHaveKey('Home', message: 'once, in place of the shared one')
    ;
});

it('skips routes while routes are cached', function (): void {
    (new Filesystem())->ensureDirectoryExists("{$this->base}/bootstrap/cache");
    (new Filesystem())->put($this->app->getCachedRoutesPath(), '<?php');
    ($this->bootNews)();

    expect($this->app->make('router')->getRoutes())->toHaveCount(0);
});

it('loads routes otherwise', function (): void {
    ($this->bootNews)();

    $uris = array_map(
        static fn ($route): string => $route->uri(),
        $this->app->make('router')->getRoutes()->getRoutes(),
    );

    expect($uris)->toContain('desks.json');
});
