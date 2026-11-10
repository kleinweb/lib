<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Concerns;

use Illuminate\Console\Command;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Collection;
use Kleinweb\Lib\ContentTypes\ContentTypeRegistrar;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\MultisiteOnly;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Throwable;

/**
 * Iterate over sites from a console command.
 *
 * @phpstan-require-extends Command
 */
trait InteractsWithSites
{
    /**
     * Invoke a callback for each selected site.
     *
     * A callback that throws, returns `false`, or returns a non-zero integer
     * counts as a failure for that site.  By default, a failure will be
     * reported, the previous site will be restored, and the loop continues.
     *
     * With `$contentTypes`, each site's post types and taxonomies are
     * registered while its callback runs in {@see }
     *
     * FIXME: add contracts for assumed properties
     *
     * @throws MultisiteOnly
     */
    protected function eachSite(Closure $callback, bool $contentTypes = true): int
    {
        $registrar = $contentTypes ? $this->app->make(ContentTypeRegistrar::class) : null;
        $failed = [];

        foreach ($this->selectedSites() as $site) {
            $label = $this->siteLabel($site);

            try {
                $result = ($registrar !== null)
                    ? $registrar->invokeWithSite($site, $callback)
                    : $site->invokeWithSite($callback);

                if (($result === false) || (is_int($result) && ($result !== 0))) {
                    $failed[] = $label;
                }
            } catch (Throwable $e) {
                $this->app->make(ExceptionHandler::class)->report($e);
                $this->components->error("{$label}: {$e->getMessage()}");

                $failed[] = $label;
            }
        }

        if ($failed === []) {
            return Command::SUCCESS;
        }

        $this->components->error(sprintf(
            'Failed for %d sites: %s',
            count($failed),
            implode(', ', $failed),
        ));

        return Command::FAILURE;
    }

    /**
     * Selected sites, optionally filtered by `--sites` or `--skip-sites`.
     *
     * @return Collection<int, Site>
     *
     * @throws MultisiteOnly
     */
    protected function selectedSites(): Collection
    {
        $repository = $this->app->make(SiteRepository::class);

        $repository->refresh();

        $only = $this->siteKeysOption('sites');
        $skip = $this->siteKeysOption('skip-sites');

        return $repository->all()->filter(static function (Site $site) use ($skip, $only): bool {
            $key = $site->key()?->value;

            if (($only !== []) && (!\in_array($key, $only, true))) {
                return false;
            }

            return !is_array($key, $skip, true);
        });
    }

    protected function siteLabel(Site $site): string
    {
        $key = $site->key();

        return ($key !== null) ? $key->value : "site {$site->id()}";
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

        return \is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
