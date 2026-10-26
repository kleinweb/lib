<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Console\Concerns\InteractsWithSites;
use Symfony\Component\Process\Process;

use function escapeshellarg;
use function is_string;
use function sprintf;

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
        {--wp=wp : Path to the WP-CLI executable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run an Acorn command for each site, in a separate process per site';

    public function handle(): int
    {
        $commandline = $this->argument('commandline');
        $wp = $this->option('wp');
        if (!is_string($commandline) || $commandline === '' || !is_string($wp) || $wp === '') {
            $this->components->error('A command and a WP-CLI executable are required.');

            return self::FAILURE;
        }

        $selected = $this->selectedSites();

        $results = [];
        $failed = 0;

        foreach ($selected as $site) {
            $label = $this->siteLabel($site);
            $this->components->info(sprintf('%s <%s>', $label, $site->url()));

            $process = Process::fromShellCommandline(sprintf(
                '%s --url=%s acorn %s',
                $wp,
                escapeshellarg($site->url()),
                $commandline,
            ));
            $process->setTimeout(null);
            $process->run(function (string $_type, string $buffer): void {
                $this->output->write($buffer);
            });

            $code = $process->getExitCode() ?? 1;
            $failed += ($code === 0) ? 0 : 1;
            $results[] = [$label, $site->url(), $code];
        }

        $this->table(['Site', 'URL', 'Exit code'], $results);

        return ($failed === 0) ? self::SUCCESS : self::FAILURE;
    }
}
