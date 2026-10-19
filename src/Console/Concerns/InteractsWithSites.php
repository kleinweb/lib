<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Concerns;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Collection;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Throwable;

use function array_filter;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Loop over sites with `switch_to_blog()` from a console command.
 *
 * Add `{--sites=* : Only these site keys} {--skip-sites=* : Skip these site keys}`
 * to the command's signature to let callers choose sites.
 *
 * Each callback runs through {@see Site::run()}: WordPress data, options,
 * the per-blog Laravel cache and the site's config overlay follow the switch.
 * Providers and container bindings do not; they belong to the site the
 * command was started for.  For work that needs another site's bindings, use
 * `site:run`, which starts a process per site.
 *
 * @phpstan-require-extends Command
 */
trait InteractsWithSites
{
    /**
     * Run a callback for each selected site.
     *
     * A callback that throws, returns `false`, or returns a non-zero integer
     * counts as a failure for that site; the error is reported, the previous
     * site restored, and the loop carries on.
     *
     * @param Closure(Site): mixed $callback
     *
     * @return int `Command::SUCCESS` if every site succeeded, else `FAILURE`
     */
    protected function forEachSite(Closure $callback): int
    {
        $failed = [];

        foreach ($this->selectedSites() as $site) {
            $label = $this->siteLabel($site);

            try {
                $result = $site->run($callback);
                if ($result === false || (is_int($result) && $result !== 0)) {
                    $failed[] = $label;
                }
            } catch (Throwable $e) {
                $this->laravel->make(ExceptionHandler::class)->report($e);
                $this->components->error("{$label}: {$e->getMessage()}");
                $failed[] = $label;
            }
        }

        if ($failed === []) {
            return Command::SUCCESS;
        }

        $this->components->error(sprintf(
            'Failed for %d site(s): %s',
            count($failed),
            implode(', ', $failed),
        ));

        return Command::FAILURE;
    }

    /**
     * Sites chosen by `--sites` and `--skip-sites`, or every site.
     *
     * @return Collection<int, Site>
     */
    protected function selectedSites(): Collection
    {
        $repository = $this->laravel->make(SiteRepository::class);
        $repository->refresh();

        $only = $this->siteKeysOption('sites');
        $skip = $this->siteKeysOption('skip-sites');

        return $repository->all()->filter(static function (Site $site) use ($only, $skip): bool {
            $key = $site->key()?->value;

            if ($only !== [] && !in_array($key, $only, true)) {
                return false;
            }

            return !in_array($key, $skip, true);
        });
    }

    protected function siteLabel(Site $site): string
    {
        $key = $site->key();

        return ($key !== null) ? $key->value : "blog {$site->id()}";
    }

    /**
     * @return list<string>
     */
    private function siteKeysOption(string $name): array
    {
        if (!$this->hasOption($name)) {
            return [];
        }

        $value = $this->option($name);

        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
