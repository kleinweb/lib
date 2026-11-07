<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Contracts\Events\Dispatcher;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Console\Commands\Tenancy\CacheSites;
use Kleinweb\Lib\Console\Commands\Tenancy\CheckSites;
use Kleinweb\Lib\Console\Commands\Tenancy\ClearSites;
use Kleinweb\Lib\Support\ServiceProvider;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Roots\Acorn\Application;

/**
 * Acorn multitenancy support for per-site providers, config, and commands on a
 * WordPress multisite instance.
 *
 * Discovered automatically through `extra.acorn.providers` in this package's
 * `composer.json` file.  Non-operational on a single-site WordPress instance.
 */
final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Laravel's application service container applies `#[Bind]` attributes
        // after an environment resolver has been set.  While Laravel's `LoadConfiguration`
        // sets an environment resolver, Acorn's replacement does not, so default
        // implementations declared with `#[Bind]` would otherwise be ignored.
        if ($this->app instanceof Application) {
            $this->app->resolveEnvironmentUsing($this->app->environment(...));
        }

        $this->app->singleton(SiteRepository::class);
        $this->app->singleton(SiteManifest::class);
        $this->app->singleton(SiteContext::class, fn (): SiteContext => new SiteContext(
            $this->app->make(SiteRepository::class),
            $this->app->make(Dispatcher::class),
            $this->blogBootstrappers(),
            $this->siteBootstrappers(),
            $this->report(...),
        ));

        if (!self::isMultisite()) {
            return;
        }

        parent::register();

        $this->app->booting(function (): void {
            $this->initialize();
        });
    }

    public function boot(): void
    {
        if (!self::isMultisite()) {
            return;
        }

        // FIXME: double-check these strings (they seem not quite consistent with one another)
        $this->optimizes(optimize: 'site:cache', clear: 'site:clear', key: 'sites');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AssignSiteKeys::class,
                CacheSites::class,
                CheckSites::class,
                ClearSites::class,
            ]);
        }
    }
}
