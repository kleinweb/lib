<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * `make:site` for a key whose site plugin the example already has, so
 * nothing is written to the example: its files exist and are skipped, and
 * refused `--url`s stop before writing.
 */

declare(strict_types=1);

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Events\Dispatcher;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Console\Commands\Tenancy\MakeSite;
use Kleinweb\Lib\Tests\Support\WordPress;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function Kleinweb\Lib\Tests\testApplication;

/**
 * Through Artisan, since `--url` calls site:assign-keys.
 *
 * @param array<string, mixed> $input
 *
 * @return array{int, string}
 */
function makeSite(array $input): array
{
    $app = testApplication();
    $artisan = new Artisan($app, new Dispatcher($app), 'test');
    $artisan->setAutoExit(false);
    $artisan->resolveCommands([MakeSite::class, AssignSiteKeys::class]);

    $output = new BufferedOutput();
    $code = $artisan->find('make:site')->run(new ArrayInput($input), $output);

    return [$code, $output->fetch()];
}

beforeEach(function (): void {
    WordPress::addSite(1, 'main', domain: 'example.test');
    WordPress::addSite(2, domain: 'news.example.test');
});

it('leaves existing files alone', function (): void {
    [$code, $output] = makeSite(['key' => 'news']);

    expect($code)->toBe(Command::SUCCESS)
        ->and($output)->toContain('already exists', 'No site has the key "news" yet')
    ;
});

it('gives the key to the site at a URL', function (): void {
    [$code] = makeSite(['key' => 'news', '--url' => 'https://news.example.test/']);

    expect($code)->toBe(Command::SUCCESS)
        ->and(WordPress::$meta[2]['site_key'] ?? null)->toBe('news')
    ;
});

it('refuses before writing anything', function (
    Closure $arrange,
    string $url,
    string $expected,
): void {
    $arrange();
    [$code, $output] = makeSite(['key' => 'news', '--url' => $url]);

    expect($code)->toBe(Command::FAILURE)
        ->and($output)->toContain($expected)
        ->not->toContain('Skipped')
    ;
})->with([
    'a URL no site has' => [static function (): void {}, 'elsewhere.test', 'No site has the URL'],
    'a key another site has' => [
        static function (): void {
            WordPress::$meta[1]['site_key'] = 'news';
        },
        'news.example.test',
        'already used by site 1',
    ],
    "replacing a site's key" => [
        static function (): void {},
        'example.test',
        'already has the key "main"',
    ],
]);

it('keeps the keys it refused to change', function (): void {
    makeSite(['key' => 'news', '--url' => 'example.test']);
    WordPress::$meta[1]['site_key'] = 'news';
    makeSite(['key' => 'news', '--url' => 'news.example.test']);

    expect(WordPress::$meta[1]['site_key'])->toBe('news')
        ->and(WordPress::$meta[2])->not->toHaveKey('site_key')
    ;
});
