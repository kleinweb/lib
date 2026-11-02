<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * Site plugins load on their own site only and are never activated.
 */

declare(strict_types=1);

use Kleinweb\Lib\Tenancy\Exceptions\SitePluginActivation;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SitePluginGuard;
use Kleinweb\Lib\Tenancy\SitePluginLoader;
use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Lib\Tests\Support\WpDie;

const NEWS_PLUGIN = 'kleinweb-site-news/kleinweb-site-news.php';
const EVENTS_PLUGIN = 'kleinweb-site-events/kleinweb-site-events.php';

/**
 * The active plugin list as WordPress reads it on a blog.
 *
 * @return array<array-key, mixed>
 */
function activePluginsOn(int $blogId): array
{
    switch_to_blog($blogId);
    $active = get_option('active_plugins');
    restore_current_blog();

    return is_array($active) ? $active : [];
}

beforeEach(function (): void {
    WordPress::addSite(1, 'main', ['akismet/akismet.php']);
    WordPress::addSite(2, 'news');
    WordPress::addSite(3);
    WordPress::addSite(5, 'events');

    $this->reported = [];
    SitePluginLoader::register();
    (new SitePluginGuard(function (Throwable $e): void {
        $this->reported[] = $e;
    }))->register();
});

it('finds site plugins by key', function (): void {
    expect(SitePlugin::exists(SiteKey::from('news')))->toBeTrue()
        ->and(SitePlugin::exists(SiteKey::from('main')))->toBeFalse()
        ->and(SitePlugin::basename(SiteKey::from('news')))->toBe(NEWS_PLUGIN)
        ->and(SitePlugin::keyFor('kleinweb-site-news')?->value)->toBe('news')
    ;
});

it('shows each blog its own site plugin and no other', function (): void {
    expect(get_option('active_plugins'))->toBe(['akismet/akismet.php'])
        ->and(activePluginsOn(2))->toBe([NEWS_PLUGIN])
        ->and(activePluginsOn(5))->toBe([EVENTS_PLUGIN])
        ->and(activePluginsOn(3))->toBe([])
    ;
});

it('never stores the site plugin', function (): void {
    switch_to_blog(2);
    update_option('active_plugins', [...get_option('active_plugins'), 'akismet/akismet.php']);
    restore_current_blog();

    expect(WordPress::$options[2]['active_plugins'])->toBe(['akismet/akismet.php'])
        ->and(activePluginsOn(2))->toBe(['akismet/akismet.php', NEWS_PLUGIN])
    ;
});

it('can show the stored list without the site plugin added', function (): void {
    WordPress::$options[1]['active_plugins'][] = NEWS_PLUGIN;

    expect(SitePluginLoader::withoutOwnPlugin(SitePlugin::listedActive(...)))->toBe([NEWS_PLUGIN]);

    switch_to_blog(2);
    expect(SitePluginLoader::withoutOwnPlugin(SitePlugin::listedActive(...)))->toBe([]);
    restore_current_blog();
});

it('stops any activation of a site plugin', function (bool $networkWide): void {
    do_action('activate_plugin', NEWS_PLUGIN, $networkWide);
})->with(['per site' => false, 'network-wide' => true])->throws(WpDie::class);

it('lets other plugins activate', function (): void {
    do_action('activate_plugin', 'akismet/akismet.php', true);

    expect($this->reported)->toBe([]);
});

it('gives nobody activate or deactivate on a site plugin', function (): void {
    $caps = static fn (string $cap, string $plugin): mixed => apply_filters(
        'map_meta_cap',
        ['activate_plugins'],
        $cap,
        1,
        [$plugin],
    );

    expect($caps('activate_plugin', NEWS_PLUGIN))->toBe(['do_not_allow'])
        ->and($caps('deactivate_plugin', NEWS_PLUGIN))->toBe(['do_not_allow'])
        ->and($caps('deactivate_plugin', 'akismet/akismet.php'))->toBe(['activate_plugins'])
    ;
});

it('undoes and reports a silent network activation', function (): void {
    WordPress::$networkOptions['active_sitewide_plugins'] = ['akismet/akismet.php' => 1];
    update_site_option('active_sitewide_plugins', [
        'akismet/akismet.php' => 1,
        NEWS_PLUGIN => 2,
        'query-monitor/query-monitor.php' => 2,
    ]);

    expect(WordPress::$networkOptions['active_sitewide_plugins'])
        ->toBe(['akismet/akismet.php' => 1, 'query-monitor/query-monitor.php' => 2])
        ->and($this->reported)->toHaveCount(1)
        ->and($this->reported[0])->toBeInstanceOf(SitePluginActivation::class)
    ;
});

it('lists network-activated site plugins', function (): void {
    WordPress::$networkOptions['active_sitewide_plugins'] = [EVENTS_PLUGIN => 1, 'a/a.php' => 1];

    expect(SitePlugin::networkActivated())->toBe([EVENTS_PLUGIN]);
});
