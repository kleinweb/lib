<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Console\Commands\Tenancy\CacheSites;
use Kleinweb\Lib\Console\Commands\Tenancy\CheckSites;
use Kleinweb\Lib\Console\Commands\Tenancy\ClearSites;
use Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains;
use Kleinweb\Lib\Console\Commands\Tenancy\ListSites;
use Kleinweb\Lib\Console\Commands\Tenancy\MakeSite;
use Kleinweb\Lib\Console\Commands\Tenancy\RunForSites;
use Kleinweb\Lib\Tenancy\Bootstrappers\CacheBootstrapper;
use Kleinweb\Lib\Tenancy\Bootstrappers\ConfigBootstrapper;
use Kleinweb\Lib\Tenancy\Contracts\BlogBootstrapper;
use Kleinweb\Lib\Tenancy\Contracts\SiteBootstrapper;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Throwable;
use Webmozart\Assert\Assert;

use function add_action;
use function count;
use function get_current_blog_id;
use function function_exists;
use function is_array;
use function is_multisite;
use function ms_is_switched;

/**
 * Multisite support: per-site providers, config and commands.
 *
 * Discovered automatically through `extra.acorn.providers` in this package's
 * `composer.json`.  Does nothing on a single-site install.
 *
 * Options, all optional, in `config/tenancy.php`:
 *
 * - `bootstrappers`: {@see SiteBootstrapper} classes for keyed sites; default
 *   the config overlay.
 * - `blog_bootstrappers`: {@see BlogBootstrapper} classes for every blog;
 *   default per-blog cache isolation.
 * - `meta_config`: blogmeta key => config key, applied by the config overlay.
 */
final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Laravel's container applies `#[Bind]` attributes only once an
        // environment resolver is set.  Laravel's LoadConfiguration sets it;
        // Acorn's replacement does not, so default implementations declared
        // with `#[Bind]` would otherwise be ignored.
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

        if (!self::multisite()) {
            return;
        }

        // Runs after every configured provider has registered and before any
        // boots: site providers register last, so their bindings win, and the
        // config overlay sees package defaults already merged.
        $this->app->booting(function (): void {
            $this->initialize();
        });
    }

    public function boot(): void
    {
        if (!self::multisite()) {
            return;
        }

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

    /**
     * Register the request site's code and start following `switch_blog`.
     *
     * Failure to discover or load site code is reported and leaves the
     * request on plain WordPress behaviour; it never stops Acorn booting.
     */
    private function initialize(): void
    {
        $sites = $this->app->make(SiteRepository::class);
        $context = $this->app->make(SiteContext::class);

        $site = null;
        try {
            $site = $sites->findById(self::requestBlogId());
            if ($site?->key() !== null) {
                $this->app->make(SiteLoader::class)->load($site);
            } else {
                $site = null;
            }
        } catch (Throwable $e) {
            $this->report($e);
        }

        $context->initialize($site, get_current_blog_id());

        // Acorn's own handler (priority 10) sets `cache.prefix` and
        // `session.cookie` for the new blog first.
        add_action('switch_blog', static function (mixed $blogId) use ($context): void {
            $context->sync((int) $blogId);
        }, 20, 1);

        // Keep the site list current in long-running processes.
        $refresh = static function () use ($sites): void {
            $sites->refresh();
        };
        add_action('wp_initialize_site', $refresh);
        add_action('wp_update_site', $refresh);
        add_action('wp_delete_site', $refresh);

        $this->app->booted(static function () use ($context): void {
            $context->announce();
        });
    }

    /**
     * The blog WordPress loaded, even if initialization runs mid-switch.
     */
    private static function requestBlogId(): int
    {
        $stack = $GLOBALS['_wp_switched_stack'] ?? [];

        return (ms_is_switched() && is_array($stack) && count($stack) > 0)
            ? (int) $stack[0]
            : get_current_blog_id();
    }

    /**
     * @return list<BlogBootstrapper>
     */
    private function blogBootstrappers(): array
    {
        $instances = [];
        $classes = $this->classes('tenancy.blog_bootstrappers', [CacheBootstrapper::class]);
        foreach ($classes as $class) {
            $instance = $this->app->make($class);
            Assert::isInstanceOf($instance, BlogBootstrapper::class);
            $instances[] = $instance;
        }

        return $instances;
    }

    /**
     * @return list<SiteBootstrapper>
     */
    private function siteBootstrappers(): array
    {
        $instances = [];
        foreach ($this->classes('tenancy.bootstrappers', [ConfigBootstrapper::class]) as $class) {
            $instance = $this->app->make($class);
            Assert::isInstanceOf($instance, SiteBootstrapper::class);
            $instances[] = $instance;
        }

        return $instances;
    }

    /**
     * @param list<class-string> $default
     *
     * @return list<string>
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
     */
    private function report(Throwable $e): void
    {
        try {
            $this->app->make(ExceptionHandler::class)->report($e);
        } catch (Throwable) {
            // Nothing else to do: never break the caller.
        }
    }

    private static function multisite(): bool
    {
        return function_exists('is_multisite') && is_multisite();
    }
}
