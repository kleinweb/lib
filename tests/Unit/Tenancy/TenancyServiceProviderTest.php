<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * The provider as Acorn runs it: registered with the application's
 * providers, doing its work from the `booting` callback.  Blogs: 1 `main`,
 * 2 `news` (child theme kleinweb-news), 5 `events`.
 */

declare(strict_types=1);

use App\Contracts\Masthead;
use App\Services\DefaultMasthead;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Kleinweb\Lib\ContentTypes\ContentTypesServiceProvider;
use Kleinweb\Lib\Tenancy\SiteContext;
use Kleinweb\Lib\Tenancy\TenancyServiceProvider;
use Kleinweb\Lib\Tests\Support\RecordingExceptionHandler;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Sites\News\Providers\NewsServiceProvider;
use Kleinweb\Sites\News\Services\NewsMasthead;
use Kleinweb\Themes\Base\Layout;
use Kleinweb\Themes\News\WideLayout;

use function Kleinweb\Lib\Tests\exampleConfig;
use function Kleinweb\Lib\Tests\tempPath;

/**
 * An application with what Acorn would have registered before the provider.
 */
function tenancyApplication(Config $config, ExceptionHandler $handler): Application
{
    $app = new Application(tempPath('app'));
    $app->instance('config', $config);
    $app->instance('files', new Filesystem());
    $app->instance(ExceptionHandler::class, $handler);
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);

    return $app;
}

/**
 * Boot for a request to a blog; `null` leaves the current blog as it is.
 */
function bootTenancy(Application $app, ?int $blogId, string $stylesheet = 'kleinweb-base'): void
{
    if ($blogId !== null) {
        WordPress::$blogId = $blogId;
    }

    WordPress::$stylesheet = constant('WP_CONTENT_DIR') . "/themes/{$stylesheet}";
    $app->register(TenancyServiceProvider::class);
    $app->boot();
}

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(5, 'events');

    $this->config = new Config([
        'mail' => ['default' => 'log', 'from' => ['address' => 'shared@x', 'name' => 'Shared']],
        'post-types' => exampleConfig('post-types'),
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
    ]);
    $this->handler = new RecordingExceptionHandler();
    $this->app = tenancyApplication($this->config, $this->handler);
    $this->mailFrom = fn (): mixed => $this->config->get('mail.from.address');
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
});

it('gives a request for news the news site and nothing else', function (): void {
    bootTenancy($this->app, 2, 'kleinweb-news');

    expect($this->app->providerIsLoaded(NewsServiceProvider::class))->toBeTrue()
        ->and($this->app->make(Masthead::class))->toBeInstanceOf(NewsMasthead::class)
        ->and($this->app->make(Layout::class))->toBeInstanceOf(WideLayout::class)
        ->and(($this->mailFrom)())->toBe('news@klein.temple.edu')
        ->and($this->app->make(SiteContext::class)->initialized()?->key()?->value)->toBe('news')
        ->and($this->handler->reported)->toBe([])
    ;
});

it('adds site hooks only for their site', function (): void {
    bootTenancy($this->app, 2, 'kleinweb-news');
    expect(WordPress::$hooks['excerpt_length'] ?? [])->not->toBeEmpty();

    WordPress::reset();
    WordPress::addSite(1, 'main');
    WordPress::addSite(5, 'events');
    bootTenancy(tenancyApplication($this->config, $this->handler), 5);
    expect(WordPress::$hooks['excerpt_length'] ?? [])->toBeEmpty();
});

it('gives a request for another site the shared defaults', function (): void {
    bootTenancy($this->app, 5);

    expect($this->app->providerIsLoaded(NewsServiceProvider::class))->toBeFalse()
        ->and($this->app->make(Masthead::class))->toBeInstanceOf(DefaultMasthead::class)
        ->and($this->app->make(Layout::class)::class)->toBe(Layout::class)
        ->and($this->config->get('services.ticketing.endpoint'))
        ->toBe('https://tickets.example.test/api')
    ;
});

it('lets config follow switches but not bindings', function (): void {
    bootTenancy($this->app, 2, 'kleinweb-news');

    switch_to_blog(1);
    expect(($this->mailFrom)())->toBe('shared@x')
        ->and($this->app->make(Masthead::class))->toBeInstanceOf(NewsMasthead::class)
    ;
    restore_current_blog();

    expect(($this->mailFrom)())->toBe('news@klein.temple.edu');
});

it('loads the request site even when booted mid-switch', function (): void {
    // WordPress loaded news; something switched to main before Acorn booted.
    WordPress::$blogId = 2;
    switch_to_blog(1);
    bootTenancy($this->app, null, 'kleinweb-news');

    expect($this->app->providerIsLoaded(NewsServiceProvider::class))->toBeTrue()
        ->and(($this->mailFrom)())->toBe('shared@x', 'still on main')
    ;

    restore_current_blog();
    expect(($this->mailFrom)())->toBe('news@klein.temple.edu');
});

it("registers post types on init from the site's config", function (): void {
    $this->app->register(ContentTypesServiceProvider::class);
    bootTenancy($this->app, 2, 'kleinweb-news');
    do_action('init');

    expect(post_type_exists('story'))->toBeTrue()
        ->and(post_type_exists('profile'))->toBeTrue()
        ->and(post_type_exists('event'))->toBeFalse()
    ;
});
