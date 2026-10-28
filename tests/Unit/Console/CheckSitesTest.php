<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Console\Commands\Tenancy\CheckSites;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SitePluginLoader;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function sys_get_temp_dir;
use function uniqid;

/**
 * `site:check` against the example project.
 */
final class CheckSitesTest extends TestCase
{
    private const string NEWS = 'kleinweb-site-news/kleinweb-site-news.php';

    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main');
        WordPress::addSite(2, 'news');
        WordPress::addSite(5, 'events');
        SitePluginLoader::register();
    }

    public function testTheExampleProjectIsClean(): void
    {
        [$code, $output] = $this->check();

        self::assertSame(Command::SUCCESS, $code, $output);
        self::assertStringContainsString('No problems found', $output);
    }

    public function testReportsAMissingLoader(): void
    {
        WordPress::$hooks = [];

        self::assertProblem('SitePluginLoader::register() is not called', ...$this->check());
    }

    public function testReportsStoredAndNetworkActivations(): void
    {
        WordPress::$options[1]['active_plugins'] = [self::NEWS];
        WordPress::$options[2]['active_plugins'] = [self::NEWS];
        WordPress::$networkOptions['active_sitewide_plugins'] = [self::NEWS => 1];

        [$code, $output] = $this->check();

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('is network-activated', $output);
        self::assertStringContainsString('on site 1;', $output);
        self::assertStringContainsString('on site 2;', $output, 'stored on its own site too');
    }

    public function testReportsKeyProblems(): void
    {
        WordPress::addSite(6, 'news');
        WordPress::addSite(7, 'Not Valid');
        WordPress::addSite(8);
        WordPress::addSite(9, 'www');

        [$code, $output] = $this->check();

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('key "news" is used by another site', $output);
        self::assertStringContainsString('invalid key "Not Valid"', $output);
        self::assertStringContainsString('no key; run site:assign-keys', $output);
        self::assertStringContainsString('key "www" is reserved', $output);
    }

    public function testReportsASitePluginNoSiteHas(): void
    {
        unset(WordPress::$sites[5], WordPress::$meta[5], WordPress::$options[5]);

        self::assertProblem('no site has a matching key', ...$this->check());
    }

    private static function assertProblem(string $expected, int $code, string $output): void
    {
        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString($expected, $output);
    }

    /**
     * @return array{int, string}
     */
    private function check(): array
    {
        $app = new Application(sys_get_temp_dir() . '/' . uniqid('kleinweb-check-', true));
        $app->instance('files', new Filesystem());
        $app->instance(SiteRepository::class, new SiteRepository());
        $app->instance(SiteManifest::class, new SiteManifest($app, new Filesystem()));

        $command = new CheckSites();
        $command->setLaravel($app);
        $output = new BufferedOutput();
        $code = $command->run(new ArrayInput([]), $output);

        return [$code, $output->fetch()];
    }
}
