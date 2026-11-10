<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Kleinweb\Lib\Console\Concerns\InteractsWithSites;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\MultisiteOnly;
use Symfony\Component\Process\Process;

/**
 * Invoke an Acorn command once per site, each in its own process.
 *
 * A fresh process per site allows for loading each site's providers.
 * Conversely, a same-process invocation with {@see switch_to_blog()}
 * would only switch WordPress data and configuration.
 */
#[Signature(
    'site:invoke
        {command : Acorn command invocation}
        {--sites=* : Filter sites by key}
        {--skip-sites=* : Skip sites by key}
        {--parallel=1 : Parallelism}
        {--wp=wp : Path to the WP-CLI executable}',
)]
#[Description('Invoke an Acorn command per site, in parallel')]
final class RunForSites extends Command
{
    use InteractsWithSites;

    /** @var list<array{string, string, int}> site label, URL, exit code */
    private array $results = [];

    /**
     * @throws MultisiteOnly
     */
    public function handle(): int
    {
        $command = $this->option('command');
        $wp = $this->option('wp');

        if (!is_string($command) || ($command === '')) {
            $this->components->error('Command string must not be empty.');

            return self::INVALID;
        }

        if (!is_string($wp) || ($wp === '')) {
            $this->components->error('Could not locate WP-CLI executable.');

            return self::INVALID;
        }

        $parallel = filter_var(
            $this->option('parallel'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($parallel === false) {
            $this->components->error('--parallel must be a positive integer.');

            return self::INVALID;
        }

        /** @var array<string, array{Site, Process}> $running label => [site, process] */
        $running = [];
        $pending = $this->selectedSites()->values()->all();

        while (($pending !== []) || ($running !== [])) {
            while (($pending !== []) && (count($running) < $parallel)) {
                $site = array_shift($pending);
                $process = $this->process($wp, $site, $command);
                $label = $this->siteLabel($site);

                if ($parallel === 1) {
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
                // TODO: no magic numbers
                // 0.05 seconds
                usleep(50000);
            }
        }

        $this->table(['Site', 'URL', 'Exit code'], $this->results);

        $didAnyFail = array_any(
            $this->results,
            static fn (array $row): bool => $row[2] !== self::SUCCESS,
        );

        return $didAnyFail ? self::FAILURE : self::SUCCESS;
    }

    private function process(string $wp, Site $site, string $command): Process
    {
        $process = Process::fromShellCommandline(sprintf(
            '%s --url=%s acorn %s',
            $wp,
            escapeshellarg($site->url()),
            $command,
        ));
        $process->setTimeout(null);

        return $process;
    }

    private function record(Site $site, string $label, Process $process): void
    {
        $this->results[] = [$label, $site->url(), $process->getExitCode() ?? self::FAILURE];
    }
}
