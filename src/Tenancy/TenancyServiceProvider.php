<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Contracts\Events\Dispatcher;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Support\ServiceProvider;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
                CacheSites,
            ]);
        }
    }
}
