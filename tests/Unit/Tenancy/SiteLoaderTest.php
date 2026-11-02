<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * Registering the example's site plugin and theme providers.
 */

declare(strict_types=1);

use App\Contracts\Masthead;
use App\Services\DefaultMasthead;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SiteLoader;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Sites\News\Providers\NewsServiceProvider;
use Kleinweb\Sites\News\Services\NewsMasthead;
use Kleinweb\Themes\Base\Layout;
use Kleinweb\Themes\Base\ThemeServiceProvider as BaseThemeProvider;
use Kleinweb\Themes\News\ThemeServiceProvider as NewsThemeProvider;
use Kleinweb\Themes\News\WideLayout;

/**
 * @return list<class-string>
 */
function providerClasses(Application $app): array
{
    return array_map(
        static fn (ServiceProvider $provider): string => $provider::class,
        array_values($app->getProviders(ServiceProvider::class)),
    );
}

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(5, 'events');

    $this->app = new Application(sys_get_temp_dir());
    // As TenancyServiceProvider does, so `#[Bind]` defaults apply.
    $this->app->resolveEnvironmentUsing(static fn (): bool => true);
    $framework = providerClasses($this->app);

    $this->load = function (int $blogId): void {
        $site = Site::find($blogId);
        expect($site)->not->toBeNull();

        (new SiteLoader($this->app, new SiteManifest($this->app, new Filesystem())))->load($site);
    };

    // Providers registered since the application was made, in order.
    $this->registered = fn (): array => array_values(
        array_diff(providerClasses($this->app), $framework),
    );
});

it('gives news its plugin and theme providers, most specific last', function (): void {
    WordPress::$stylesheet = constant('WP_CONTENT_DIR') . '/themes/kleinweb-news';
    ($this->load)(2);

    expect(($this->registered)())
        ->toBe([NewsServiceProvider::class, BaseThemeProvider::class, NewsThemeProvider::class])
        ->and($this->app->make(Masthead::class))->toBeInstanceOf(NewsMasthead::class)
        ->and($this->app->make(Layout::class))->toBeInstanceOf(WideLayout::class)
    ;
});

it('gives another site none of the news code', function (): void {
    ($this->load)(5);

    expect(($this->registered)())->toBe([BaseThemeProvider::class])
        ->and($this->app->make(Masthead::class))->toBeInstanceOf(DefaultMasthead::class)
        ->and($this->app->make(Layout::class)::class)->toBe(Layout::class)
    ;
});
