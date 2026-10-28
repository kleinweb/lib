<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy;

use Kleinweb\Lib\Tenancy\Exceptions\SitePluginActivation;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SitePluginGuard;
use Kleinweb\Lib\Tenancy\SitePluginLoader;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Lib\Tests\Support\WpDie;
use Throwable;

use function get_option;
use function restore_current_blog;
use function switch_to_blog;
use function update_option;
use function update_site_option;

/**
 * Site plugins load on their own site only and are never activated.
 */
final class SitePluginTest extends TestCase
{
    private const string NEWS = 'kleinweb-site-news/kleinweb-site-news.php';

    private const string EVENTS = 'kleinweb-site-events/kleinweb-site-events.php';

    /** @var list<Throwable> */
    private array $reported = [];

    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main', ['akismet/akismet.php']);
        WordPress::addSite(2, 'news');
        WordPress::addSite(3);
        WordPress::addSite(5, 'events');

        SitePluginLoader::register();
        (new SitePluginGuard(function (Throwable $e): void {
            $this->reported[] = $e;
        }))->register();
    }

    public function testExistsByKey(): void
    {
        self::assertTrue(SitePlugin::exists(SiteKey::from('news')));
        self::assertFalse(SitePlugin::exists(SiteKey::from('main')));
        self::assertSame(self::NEWS, SitePlugin::basename(SiteKey::from('news')));
        self::assertSame('news', SitePlugin::keyFor('kleinweb-site-news')?->value);
    }

    public function testEachBlogSeesOnlyItsOwnSitePlugin(): void
    {
        self::assertSame(['akismet/akismet.php'], get_option('active_plugins'));

        self::assertSame([self::NEWS], $this->activeOn(2));
        self::assertSame([self::EVENTS], $this->activeOn(5));
        self::assertSame([], $this->activeOn(3));
    }

    public function testTheOwnPluginIsNeverStored(): void
    {
        switch_to_blog(2);
        update_option('active_plugins', [...get_option('active_plugins'), 'akismet/akismet.php']);
        restore_current_blog();

        self::assertSame(['akismet/akismet.php'], WordPress::$options[2]['active_plugins']);
        self::assertSame(['akismet/akismet.php', self::NEWS], $this->activeOn(2));
    }

    public function testStoredActivationsAreVisibleWithoutTheOwnPlugin(): void
    {
        WordPress::$options[1]['active_plugins'][] = self::NEWS;

        self::assertSame(
            [self::NEWS],
            SitePluginLoader::withoutOwnPlugin(SitePlugin::listedActive(...)),
        );

        switch_to_blog(2);
        self::assertSame([], SitePluginLoader::withoutOwnPlugin(SitePlugin::listedActive(...)));
        restore_current_blog();
    }

    public function testActivationDies(): void
    {
        $this->expectException(WpDie::class);
        \do_action('activate_plugin', self::NEWS, false);
    }

    public function testNetworkActivationDies(): void
    {
        $this->expectException(WpDie::class);
        \do_action('activate_plugin', self::NEWS, true);
    }

    public function testOtherPluginsActivate(): void
    {
        \do_action('activate_plugin', 'akismet/akismet.php', true);
        self::assertSame([], $this->reported);
    }

    public function testNobodyMayActivateOrDeactivateASitePlugin(): void
    {
        $caps = static fn (string $cap, string $plugin): mixed => \apply_filters(
            'map_meta_cap',
            ['activate_plugins'],
            $cap,
            1,
            [$plugin],
        );

        self::assertSame(['do_not_allow'], $caps('activate_plugin', self::NEWS));
        self::assertSame(['do_not_allow'], $caps('deactivate_plugin', self::NEWS));
        self::assertSame(['activate_plugins'], $caps('deactivate_plugin', 'akismet/akismet.php'));
    }

    public function testSilentNetworkActivationIsUndoneAndReported(): void
    {
        WordPress::$networkOptions['active_sitewide_plugins'] = ['akismet/akismet.php' => 1];
        update_site_option('active_sitewide_plugins', [
            'akismet/akismet.php' => 1,
            self::NEWS => 2,
            'query-monitor/query-monitor.php' => 2,
        ]);

        self::assertSame(
            ['akismet/akismet.php' => 1, 'query-monitor/query-monitor.php' => 2],
            WordPress::$networkOptions['active_sitewide_plugins'],
        );
        self::assertCount(1, $this->reported);
        self::assertInstanceOf(SitePluginActivation::class, $this->reported[0]);
    }

    public function testNetworkActivatedSitePluginsAreListed(): void
    {
        WordPress::$networkOptions['active_sitewide_plugins'] = [self::EVENTS => 1, 'a/a.php' => 1];

        self::assertSame([self::EVENTS], SitePlugin::networkActivated());
    }

    /**
     * @return array<array-key, mixed>
     */
    private function activeOn(int $blogId): array
    {
        switch_to_blog($blogId);
        $active = get_option('active_plugins');
        restore_current_blog();

        self::assertIsArray($active);

        return $active;
    }
}
