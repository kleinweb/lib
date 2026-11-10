<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Console\Commands\Tenancy\CacheSites;
use Kleinweb\Lib\Console\Commands\Tenancy\CheckSites;
use Kleinweb\Lib\Console\Commands\Tenancy\ClearSites;
use Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains;
use Kleinweb\Lib\Console\Commands\Tenancy\ListSites;
use Kleinweb\Lib\Console\Commands\Tenancy\MakeSite;
use Kleinweb\Lib\Console\Commands\Tenancy\RunForSites;
use Kleinweb\Lib\Support\ServiceProvider;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Roots\Acorn\Application;
use Webmozart\Assert\Assert;
use Throwable;

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
                DemapDomains::class,
                ListSites::class,
                MakeSite::class,
                RunForSites::class,
            ]);
        }
    }

    private function initialize(): void
    {
        $sites = $this->app->make(SiteRepository::class);
        $context = $this->app->make(SiteContext::class);

        $site = null;

        //        try {
        //            // TODO $site = $sites->findById()
        //        }
    }

    /**
     * @param list<class-string> $default
     *
     * @return list<string>
     *
     * @throws BindingResolutionException
     */
    private function classes(string $key, array $default): array
    {
        $classes = $this->app->make('config')->get($key, $default);

        Assert::isList($classes);
        Assert::allString($classes);

        return $classes;
    }

    /**
     * Report without letting a failing reporter escape.
     *
     * FIXME: needs justification
     */
    private function report(Throwable $e): void
    {
        try {
            $this->app->make(ExceptionHandler::class)->report($e);
        } catch (Throwable $e) {
            // Nothing more to do.
        }
    }

    private static function multisite(): bool
    {
        return function_exists('is_multisite') && is_multisite();
    }
}
