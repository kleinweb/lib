<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy;

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
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Sites\News\Providers\NewsServiceProvider;
use Kleinweb\Sites\News\Services\NewsMasthead;
use Kleinweb\Themes\Base\Layout;
use Kleinweb\Themes\News\WideLayout;

use function do_action;
use function post_type_exists;
use function restore_current_blog;
use function switch_to_blog;
use function sys_get_temp_dir;
use function uniqid;

/**
 * The provider as Acorn runs it: registered with the application's
 * providers, doing its work from the `booting` callback.  Blogs: 1 `main`,
 * 2 `news` (child theme kleinweb-news), 5 `events`.
 */
final class TenancyServiceProviderTest extends TestCase
{
    private Application $app;

    private Config $config;

    private RecordingExceptionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main');
        WordPress::addSite(2, 'news');
        WordPress::addSite(5, 'events');

        $this->app = new Application(sys_get_temp_dir() . '/' . uniqid('kleinweb-app-', true));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);

        $this->config = new Config([
            'mail' => ['default' => 'log', 'from' => ['address' => 'shared@x', 'name' => 'Shared']],
            'post-types' => self::exampleConfig('post-types'),
            'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
        ]);
        $this->handler = new RecordingExceptionHandler();
        $this->bindBasics();
    }

    /**
     * What Acorn would have registered before this provider.
     */
    private function bindBasics(): void
    {
        $this->app->instance('config', $this->config);
        $this->app->instance('files', new Filesystem());
        $this->app->instance(ExceptionHandler::class, $this->handler);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    public function testARequestForNewsGetsTheNewsSiteAndNothingElse(): void
    {
        $this->boot(blogId: 2, stylesheet: 'kleinweb-news');

        self::assertTrue($this->app->providerIsLoaded(NewsServiceProvider::class));
        self::assertInstanceOf(NewsMasthead::class, $this->app->make(Masthead::class));
        self::assertInstanceOf(WideLayout::class, $this->app->make(Layout::class));
        self::assertSame('news@klein.temple.edu', $this->config->get('mail.from.address'));
        $context = $this->app->make(SiteContext::class);
        self::assertSame('news', $context->initialized()?->key()?->value);
        self::assertSame([], $this->handler->reported);
    }

    public function testSiteHooksAreAddedOnlyForTheirSite(): void
    {
        $this->boot(blogId: 2, stylesheet: 'kleinweb-news');
        self::assertNotEmpty(WordPress::$hooks['excerpt_length'] ?? []);

        WordPress::reset();
        WordPress::addSite(1, 'main');
        WordPress::addSite(5, 'events');
        $this->app = new Application(sys_get_temp_dir());
        $this->bindBasics();
        $this->boot(blogId: 5);
        self::assertEmpty(WordPress::$hooks['excerpt_length'] ?? []);
    }

    public function testARequestForAnotherSiteGetsTheSharedDefaults(): void
    {
        $this->boot(blogId: 5);

        self::assertFalse($this->app->providerIsLoaded(NewsServiceProvider::class));
        self::assertInstanceOf(DefaultMasthead::class, $this->app->make(Masthead::class));
        self::assertSame(Layout::class, $this->app->make(Layout::class)::class);
        $ticketing = $this->config->get('services.ticketing.endpoint');
        self::assertSame('https://tickets.example.test/api', $ticketing);
    }

    public function testConfigFollowsSwitchesButBindingsDoNot(): void
    {
        $this->boot(blogId: 2, stylesheet: 'kleinweb-news');

        switch_to_blog(1);
        self::assertSame('shared@x', $this->config->get('mail.from.address'));
        self::assertInstanceOf(NewsMasthead::class, $this->app->make(Masthead::class));
        restore_current_blog();

        self::assertSame('news@klein.temple.edu', $this->config->get('mail.from.address'));
    }

    public function testBootingMidSwitchStillLoadsTheRequestSite(): void
    {
        // WordPress loaded news; something switched to main before Acorn booted.
        WordPress::$blogId = 2;
        switch_to_blog(1);
        $this->boot(blogId: null, stylesheet: 'kleinweb-news');

        self::assertTrue($this->app->providerIsLoaded(NewsServiceProvider::class));
        self::assertSame('shared@x', $this->config->get('mail.from.address'), 'still on main');

        restore_current_blog();
        self::assertSame('news@klein.temple.edu', $this->config->get('mail.from.address'));
    }

    public function testPostTypesRegisterOnInitFromTheSitesConfig(): void
    {
        $this->app->register(ContentTypesServiceProvider::class);
        $this->boot(blogId: 2, stylesheet: 'kleinweb-news');
        do_action('init');

        self::assertTrue(post_type_exists('story'));
        self::assertTrue(post_type_exists('profile'));
        self::assertFalse(post_type_exists('event'));
    }

    private function boot(?int $blogId, string $stylesheet = 'kleinweb-base'): void
    {
        if ($blogId !== null) {
            WordPress::$blogId = $blogId;
        }

        WordPress::$stylesheet = \constant('WP_CONTENT_DIR') . "/themes/{$stylesheet}";
        $this->app->register(TenancyServiceProvider::class);
        $this->app->boot();
    }
}
