<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Console\Commands\Tenancy\RunForSites;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class RunForSitesTest extends TestCase
{
    private string $wp;

    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main');
        WordPress::addSite(2, 'news');
        WordPress::addSite(5, 'events');

        // Stands in for WP-CLI: prints its arguments, fails for site 5.
        $this->wp = sys_get_temp_dir() . '/' . uniqid('fake-wp-', true);
        file_put_contents($this->wp, <<<'SH'
            #!/bin/sh
            echo "ran $1 $3"
            case "$1" in *site5*) exit 3 ;; esac
            SH);
    }

    protected function tearDown(): void
    {
        unlink($this->wp);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function parallelism(): iterable
    {
        yield 'one at a time' => [1];
        yield 'all at once' => [3];
    }

    #[DataProvider('parallelism')]
    public function testRunsEachSiteInItsOwnProcessAndReportsFailures(int $parallel): void
    {
        [$code, $output] = $this->artisan([
            'commandline' => 'cache:clear',
            '--parallel' => (string) $parallel,
        ]);

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('ran --url=https://site1.test/ cache:clear', $output);
        self::assertStringContainsString('ran --url=https://site2.test/ cache:clear', $output);
        self::assertStringContainsString('ran --url=https://site5.test/ cache:clear', $output);
        $failedRow = '#events\s*\|\s*https://site5\.test/\s*\|\s*3\s*\|#';
        self::assertMatchesRegularExpression($failedRow, $output);
    }

    public function testSelectsSitesByKey(): void
    {
        [$code, $output] = $this->artisan(['commandline' => 'cache:clear', '--sites' => ['news']]);

        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('site2.test', $output);
        self::assertStringNotContainsString('site1.test', $output);
    }

    public function testRefusesANonPositiveParallelism(): void
    {
        [$code] = $this->artisan(['commandline' => 'cache:clear', '--parallel' => '0']);

        self::assertSame(Command::FAILURE, $code);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{int, string}
     */
    private function artisan(array $input): array
    {
        $container = new Application(sys_get_temp_dir());
        $container->instance(SiteRepository::class, new SiteRepository());

        $command = new RunForSites();
        $command->setLaravel($container);

        $output = new BufferedOutput();
        // Through sh, as the command line is run by a shell.
        $input = [...$input, '--wp' => 'sh ' . $this->wp];
        $code = $command->run(new ArrayInput($input), $output);

        return [$code, $output->fetch()];
    }
}
