<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Console\Concerns\InteractsWithSites;
use Kleinweb\Lib\Database\Models\Site;
use Symfony\Component\Process\Process;

use function array_filter;
use function array_shift;
use function count;
use function escapeshellarg;
use function filter_var;
use function is_string;
use function sprintf;
use function usleep;

use const FILTER_VALIDATE_INT;

/**
 * Run an Acorn command once per site, each in its own process.
 *
 * A fresh process per site is what loads that site's providers; running
 * in-process with `switch_to_blog()` would only switch WordPress data and
 * config.
 */
final class RunForSites extends Command
{
    use InteractsWithSites;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site:run
        {commandline : The Acorn command to run, quoted, e.g. "cache:clear"}
        {--sites=* : Only run for these site keys}
        {--skip-sites=* : Skip these site keys}
        {--parallel=1 : Run this many sites at once; output is shown per site as each finishes}
        {--wp=wp : Path to the WP-CLI executable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run an Acorn command for each site, in a separate process per site';

    /** @var list<array{string, string, int}> site label, URL, exit code */
    private array $results = [];

    public function handle(): int
    {
        $commandline = $this->argument('commandline');
        $wp = $this->option('wp');
        if (!is_string($commandline) || $commandline === '' || !is_string($wp) || $wp === '') {
            $this->components->error('A command and a WP-CLI executable are required.');

            return self::FAILURE;
        }

        $parallel = filter_var(
            $this->option('parallel'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        if ($parallel === false) {
            $this->components->error('--parallel must be a positive integer.');

            return self::FAILURE;
        }

        /** @var array<string, array{Site, Process}> $running label => [site, process] */
        $running = [];
        $pending = $this->selectedSites()->values()->all();

        while ($pending !== [] || $running !== []) {
            while ($pending !== [] && count($running) < $parallel) {
                $site = array_shift($pending);
                $process = $this->process($wp, $site, $commandline);
                $label = $this->siteLabel($site);

                if ($parallel === 1) {
                    // One at a time: stream output as it comes.
                    $this->components->info(sprintf('%s <%s>', $label, $site->url()));
                    $process->run(function (string $_type, string $buffer): void {
                        $this->output->write($buffer);
                    });
                    $this->record($site, $label, $process);

                    continue;
                }

                $process->start();
                $running[$label] = [$site, $process];
            }

            foreach ($running as $label => [$site, $process]) {
                if ($process->isRunning()) {
                    continue;
                }

                unset($running[$label]);
                $this->components->info(sprintf('%s <%s>', $label, $site->url()));
                $this->output->write($process->getOutput() . $process->getErrorOutput());
                $this->record($site, $label, $process);
            }

            if ($running !== []) {
                usleep(50000);
            }
        }

        $this->table(['Site', 'URL', 'Exit code'], $this->results);
        $failed = array_filter($this->results, static fn (array $row): bool => $row[2] !== 0);

        return ($failed === []) ? self::SUCCESS : self::FAILURE;
    }

    private function process(string $wp, Site $site, string $commandline): Process
    {
        $process = Process::fromShellCommandline(sprintf(
            '%s --url=%s acorn %s',
            $wp,
            escapeshellarg($site->url()),
            $commandline,
        ));
        $process->setTimeout(null);

        return $process;
    }

    private function record(Site $site, string $label, Process $process): void
    {
        $this->results[] = [$label, $site->url(), $process->getExitCode() ?? 1];
    }
}
