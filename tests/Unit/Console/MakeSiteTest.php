<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Console;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Console\Commands\Tenancy\MakeSite;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function sys_get_temp_dir;

/**
 * `make:site` for a key whose site plugin the example already has, so
 * nothing is written to the example: its files exist and are skipped, and
 * refused `--url`s stop before writing.
 */
final class MakeSiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main', domain: 'example.test');
        WordPress::addSite(2, domain: 'news.example.test');
    }

    public function testLeavesExistingFilesAlone(): void
    {
        [$code, $output] = $this->make(['key' => 'news']);

        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('already exists', $output);
        self::assertStringContainsString('No site has the key "news" yet', $output);
    }

    public function testGivesTheKeyToTheSiteAtAUrl(): void
    {
        [$code] = $this->make(['key' => 'news', '--url' => 'https://news.example.test/']);

        self::assertSame(Command::SUCCESS, $code);
        self::assertSame('news', WordPress::$meta[2]['site_key'] ?? null);
    }

    public function testRefusesAUrlNoSiteHas(): void
    {
        [$code, $output] = $this->make(['key' => 'news', '--url' => 'elsewhere.test']);

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('No site has the URL', $output);
        self::assertStringNotContainsString('Skipped', $output, 'checked before writing');
    }

    public function testRefusesAKeyAnotherSiteHasBeforeWritingAnything(): void
    {
        WordPress::$meta[1]['site_key'] = 'news';

        [$code, $output] = $this->make(['key' => 'news', '--url' => 'news.example.test']);

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('already used by site 1', $output);
        self::assertStringNotContainsString('Skipped', $output);
        self::assertArrayNotHasKey('site_key', WordPress::$meta[2]);
    }

    public function testRefusesToReplaceASitesKey(): void
    {
        [$code, $output] = $this->make(['key' => 'news', '--url' => 'example.test']);

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('already has the key "main"', $output);
        self::assertSame('main', WordPress::$meta[1]['site_key']);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{int, string}
     */
    private function make(array $input): array
    {
        $app = new Application(sys_get_temp_dir());
        $app->instance(SiteRepository::class, new SiteRepository());

        // `--url` calls site:assign-keys, so both need an Artisan application.
        $artisan = new Artisan($app, new Dispatcher($app), 'test');
        $artisan->setAutoExit(false);
        $artisan->resolveCommands([MakeSite::class, AssignSiteKeys::class]);
        $artisan->getLaravel()->instance('files', new Filesystem());

        $output = new BufferedOutput();
        $code = $artisan->find('make:site')->run(new ArrayInput($input), $output);

        return [$code, $output->fetch()];
    }
}
