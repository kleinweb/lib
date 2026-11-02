<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * `site:check` against the example project.
 */

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Kleinweb\Lib\Console\Commands\Tenancy\CheckSites;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SitePluginLoader;
use Kleinweb\Lib\Tests\Support\WordPress;

use function Kleinweb\Lib\Tests\runCommand;
use function Kleinweb\Lib\Tests\tempPath;
use function Kleinweb\Lib\Tests\testApplication;

/**
 * @return array{int, string}
 */
function checkSites(): array
{
    $app = testApplication(tempPath('check'));
    $app->instance(SiteManifest::class, new SiteManifest($app, new Filesystem()));

    return runCommand(new CheckSites(), $app);
}

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(5, 'events');
    SitePluginLoader::register();
});

it('finds no problems in the example project', function (): void {
    [$code, $output] = checkSites();

    expect($output)->toContain('No problems found')
        ->and($code)->toBe(Command::SUCCESS)
    ;
});

it('reports a problem', function (Closure $arrange, array $expected): void {
    $arrange();
    [$code, $output] = checkSites();

    expect($code)->toBe(Command::FAILURE)
        ->and($output)->toContain(...$expected)
    ;
})->with([
    'a missing loader' => [
        static function (): void {
            WordPress::$hooks = [];
        },
        ['SitePluginLoader::register() is not called'],
    ],
    'stored and network activations' => [
        static function (): void {
            $news = 'kleinweb-site-news/kleinweb-site-news.php';
            WordPress::$options[1]['active_plugins'] = [$news];
            WordPress::$options[2]['active_plugins'] = [$news];
            WordPress::$networkOptions['active_sitewide_plugins'] = [$news => 1];
        },
        // Stored on its own site (2) counts too.
        ['is network-activated', 'on site 1;', 'on site 2;'],
    ],
    'key problems' => [
        static function (): void {
            WordPress::addSite(6, 'news');
            WordPress::addSite(7, 'Not Valid');
            WordPress::addSite(8);
            WordPress::addSite(9, 'www');
        },
        [
            'key "news" is used by another site',
            'invalid key "Not Valid"',
            'no key; run site:assign-keys',
            'key "www" is reserved',
        ],
    ],
    'a site plugin no site has' => [
        static function (): void {
            unset(WordPress::$sites[5], WordPress::$meta[5], WordPress::$options[5]);
        },
        ['no site has a matching key'],
    ],
]);
