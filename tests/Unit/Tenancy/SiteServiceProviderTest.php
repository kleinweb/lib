<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy;

use App\Contracts\Masthead;
use App\Services\DefaultMasthead;
use App\View\Composers\SiteHeader;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Factory;
use Illuminate\View\ViewServiceProvider;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Sites\News\Providers\NewsServiceProvider;

use function sys_get_temp_dir;
use function uniqid;

/**
 * The news site plugin's provider against a real view factory.
 */
final class SiteServiceProviderTest extends TestCase
{
    private Application $app;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        WordPress::addSite(1, 'news');

        $this->base = sys_get_temp_dir() . '/' . uniqid('kleinweb-views-', true);
        $this->app = new Application($this->base);
        Facade::setFacadeApplication($this->app);
        $this->app->instance('files', new Filesystem());
        $this->app->instance('config', new Config(['view' => [
            'paths' => [self::example('wp-content/mu-plugins/example/resources/views')],
            'compiled' => "{$this->base}/views",
        ]]));
        $this->app->register(ViewServiceProvider::class);
        $this->app->bind(Masthead::class, DefaultMasthead::class);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        (new Filesystem())->deleteDirectory($this->base);
    }

    public function testSiteViewsOverrideSharedOnesEvenOnceTheFactoryExists(): void
    {
        // As in Acorn: the factory is resolved (by the view provider attaching
        // composers) before site providers boot.
        $factory = $this->factory();
        $this->bootNews();

        self::assertStringContainsString(
            '/kleinweb-site-news/resources/views/partials/masthead.blade.php',
            $factory->getFinder()->find('partials.masthead'),
        );
    }

    public function testAComposerExtendingASharedOneReplacesIt(): void
    {
        $factory = $this->factory();
        $factory->composer(SiteHeader::class::views(), SiteHeader::class);
        $this->bootNews();

        $view = $factory->make('partials.masthead');
        $factory->callComposer($view);
        $links = $view->getData()['links'] ?? null;

        self::assertIsArray($links);
        self::assertArrayHasKey('Stories', $links, 'the news composer ran');
        self::assertArrayHasKey('Home', $links, 'once, in place of the shared one');
    }

    public function testRoutesAreSkippedWhileRoutesAreCached(): void
    {
        (new Filesystem())->ensureDirectoryExists("{$this->base}/bootstrap/cache");
        (new Filesystem())->put($this->app->getCachedRoutesPath(), '<?php');
        $this->bootNews();

        self::assertCount(0, $this->app->make('router')->getRoutes());
    }

    public function testRoutesLoadOtherwise(): void
    {
        $this->bootNews();

        $uris = [];
        foreach ($this->app->make('router')->getRoutes() as $route) {
            $uris[] = $route->uri();
        }

        self::assertContains('desks.json', $uris);
    }

    private function factory(): Factory
    {
        $factory = $this->app->make('view');
        self::assertInstanceOf(Factory::class, $factory);

        return $factory;
    }

    private function bootNews(): void
    {
        $this->app->register(NewsServiceProvider::class);
        $this->app->boot();
    }
}
