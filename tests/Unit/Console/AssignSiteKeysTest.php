<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function sys_get_temp_dir;

/**
 * Network: 1 the main site (temple-news.test), 2 a subdomain, 3 a
 * subdirectory on the main host, 4 a demapped site with its original host
 * recorded, 5 www, 6 a second subdomain deriving the same key as 2.
 */
final class AssignSiteKeysTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, domain: 'temple-news.test');
        WordPress::addSite(2, domain: 'longform.temple-news.test');
        WordPress::addSite(3, domain: 'temple-news.test', path: '/owlery/');
        WordPress::addSite(4, domain: 'thecherry.org');
        WordPress::$meta[4]['orig_host'] = 'thecherry.temple-news.test';
        WordPress::addSite(5, domain: 'www.thecherry.org');
        WordPress::addSite(6, domain: 'longform.example.org');
    }

    public function testDerivesKeysLikeDemapDomains(): void
    {
        [$code, $output] = $this->assign();

        self::assertSame(Command::FAILURE, $code, 'some sites are left for --id');
        self::assertSame('longform', WordPress::$meta[2]['site_key'] ?? null);
        self::assertSame('owlery', WordPress::$meta[3]['site_key'] ?? null);
        self::assertSame('thecherry', WordPress::$meta[4]['site_key'] ?? null);
        self::assertArrayNotHasKey('site_key', WordPress::$meta[1]);
        self::assertArrayNotHasKey('site_key', WordPress::$meta[5]);
        self::assertArrayNotHasKey('site_key', WordPress::$meta[6]);
        self::assertStringContainsString('main site; assign with --id', $output);
        self::assertStringContainsString('derived key is reserved', $output);
        self::assertStringContainsString('already used by site 2', $output);
    }

    public function testDryRunWritesNothing(): void
    {
        [, $output] = $this->assign(['--dry-run' => true]);

        self::assertArrayNotHasKey('site_key', WordPress::$meta[2]);
        self::assertStringContainsString('[DRY-RUN] would assign', $output);
    }

    public function testExistingKeysAreKept(): void
    {
        WordPress::$meta[2]['site_key'] = 'ttn-longform';
        $this->assign();

        self::assertSame('ttn-longform', WordPress::$meta[2]['site_key']);
        self::assertSame('longform', WordPress::$meta[6]['site_key'] ?? null, 'now free');
    }

    public function testAssignsOneSiteExplicitly(): void
    {
        [$code] = $this->assign(['--id' => '1', '--key' => 'main']);

        self::assertSame(Command::SUCCESS, $code);
        self::assertSame('main', WordPress::$meta[1]['site_key']);
    }

    public function testExplicitAssignmentRefusesDuplicatesReservedAndOverwrites(): void
    {
        WordPress::$meta[2]['site_key'] = 'longform';

        self::assertSame(Command::FAILURE, $this->assignOne(6, 'longform'));
        self::assertSame(Command::FAILURE, $this->assignOne(5, 'www'));
        self::assertSame(Command::FAILURE, $this->assignOne(2, 'other'));
        self::assertSame('longform', WordPress::$meta[2]['site_key']);

        self::assertSame(Command::SUCCESS, $this->assignOne(2, 'other', force: true));
        self::assertSame('other', WordPress::$meta[2]['site_key']);
    }

    private function assignOne(int $id, string $key, bool $force = false): int
    {
        return $this->assign(['--id' => (string) $id, '--key' => $key, '--force' => $force])[0];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{int, string}
     */
    private function assign(array $input = []): array
    {
        $app = new Application(sys_get_temp_dir());
        $app->instance(SiteRepository::class, new SiteRepository());
        $command = new AssignSiteKeys();
        $command->setLaravel($app);
        $output = new BufferedOutput();
        $code = $command->run(new ArrayInput($input), $output);

        return [$code, $output->fetch()];
    }
}
