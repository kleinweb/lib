<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy;

use App\Contracts\Masthead;
use App\Services\DefaultMasthead;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SiteLoader;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Sites\News\Providers\NewsServiceProvider;
use Kleinweb\Sites\News\Services\NewsMasthead;
use Kleinweb\Themes\Base\Layout;
use Kleinweb\Themes\Base\ThemeServiceProvider as BaseThemeProvider;
use Kleinweb\Themes\News\ThemeServiceProvider as NewsThemeProvider;
use Kleinweb\Themes\News\WideLayout;

use function array_diff;
use function array_map;
use function array_values;
use function sys_get_temp_dir;

/**
 * Registering the example's site plugin and theme providers.
 */
final class SiteLoaderTest extends TestCase
{
    private Application $app;

    /** @var list<class-string> */
    private array $framework;

    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main');
        WordPress::addSite(2, 'news');
        WordPress::addSite(5, 'events');

        $this->app = new Application(sys_get_temp_dir());
        // As TenancyServiceProvider does, so `#[Bind]` defaults apply.
        $this->app->resolveEnvironmentUsing(static fn (): bool => true);
        $this->framework = $this->providerClasses();
    }

    public function testNewsGetsItsPluginAndChildThemeProvidersMostSpecificLast(): void
    {
        WordPress::$stylesheet = \constant('WP_CONTENT_DIR') . '/themes/kleinweb-news';
        $this->load(2);

        self::assertSame(
            [NewsServiceProvider::class, BaseThemeProvider::class, NewsThemeProvider::class],
            $this->registered(),
        );
        self::assertInstanceOf(NewsMasthead::class, $this->app->make(Masthead::class));
        self::assertInstanceOf(WideLayout::class, $this->app->make(Layout::class));
    }

    public function testAnotherSiteGetsNoneOfTheNewsCode(): void
    {
        $this->load(5);

        self::assertSame([BaseThemeProvider::class], $this->registered());
        self::assertInstanceOf(DefaultMasthead::class, $this->app->make(Masthead::class));
        self::assertSame(Layout::class, $this->app->make(Layout::class)::class);
    }

    private function load(int $blogId): void
    {
        $site = Site::find($blogId);
        self::assertNotNull($site);

        (new SiteLoader($this->app, new SiteManifest($this->app, new Filesystem())))->load($site);
    }

    /**
     * Providers registered since setUp, in order.
     *
     * @return list<class-string>
     */
    private function registered(): array
    {
        return array_values(array_diff($this->providerClasses(), $this->framework));
    }

    /**
     * @return list<class-string>
     */
    private function providerClasses(): array
    {
        return array_map(
            static fn (ServiceProvider $provider): string => $provider::class,
            array_values($this->app->getProviders(ServiceProvider::class)),
        );
    }
}
