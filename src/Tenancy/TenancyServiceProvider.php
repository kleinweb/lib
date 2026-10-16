<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Console\Commands\Tenancy\CacheSites;
use Kleinweb\Lib\Console\Commands\Tenancy\CheckSites;
use Kleinweb\Lib\Console\Commands\Tenancy\ClearSites;
use Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains;
use Kleinweb\Lib\Console\Commands\Tenancy\ListSites;
use Kleinweb\Lib\Console\Commands\Tenancy\MakeSite;
use Kleinweb\Lib\Console\Commands\Tenancy\RunForSites;
use Kleinweb\Lib\Tenancy\Bootstrappers\ConfigBootstrapper;
use Kleinweb\Lib\Tenancy\Contracts\SiteBootstrapper;
use Webmozart\Assert\Assert;

use function add_action;
use function array_map;
use function array_values;
use function function_exists;
use function is_array;
use function is_multisite;

/**
 * Multisite support: per-site providers, config and commands.
 *
 * Discovered automatically through `extra.acorn.providers` in this package's
 * `composer.json`.  Does nothing on a single-site install.
 *
 * Options, all optional, in `config/tenancy.php`:
 *
 * - `bootstrappers`: {@see SiteBootstrapper} classes run on initialize and on
 *   every switch; default the config overlay only.
 * - `meta_config`: blogmeta key => config key, applied by the config overlay.
 */
final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SiteRepository::class);
        $this->app->singleton(Discovery\SiteManifest::class);
        $this->app->singleton(SiteContext::class, function (): SiteContext {
            $classes = $this->app->make('config')
                ->get('tenancy.bootstrappers', [ConfigBootstrapper::class])
            ;

            $bootstrappers = array_map(function (mixed $class): SiteBootstrapper {
                Assert::string($class);
                $bootstrapper = $this->app->make($class);
                Assert::isInstanceOf($bootstrapper, SiteBootstrapper::class);

                return $bootstrapper;
            }, is_array($classes) ? array_values($classes) : []);

            return new SiteContext(
                $this->app->make(SiteRepository::class),
                $this->app->make(Dispatcher::class),
                $bootstrappers,
            );
        });

        if (!self::multisite()) {
            return;
        }

        // Runs after every configured provider has registered and before any
        // boots: site providers register last, so their bindings win, and the
        // config overlay sees package defaults already merged.
        $this->app->booting(function (): void {
            $this->initializeSite();
        });
    }

    public function boot(): void
    {
        if (!self::multisite()) {
            return;
        }

        $context = $this->app->make(SiteContext::class);

        // After Acorn's own handler (priority 10), which re-prefixes the cache
        // and session for the new blog.
        add_action('switch_blog', static function (mixed $blogId) use ($context): void {
            $context->switchTo((int) $blogId);
        }, 20, 1);

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

    private function initializeSite(): void
    {
        $site = $this->app->make(SiteRepository::class)->current();
        if ($site?->key() === null) {
            return;
        }

        $this->app->make(SiteLoader::class)->load($site);
        $this->app->make(SiteContext::class)->initialize($site);
    }

    private static function multisite(): bool
    {
        return function_exists('is_multisite') && is_multisite();
    }
}
