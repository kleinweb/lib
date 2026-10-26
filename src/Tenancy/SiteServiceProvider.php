<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Factory;
use Kleinweb\Lib\Support\ServiceProvider;
use ReflectionClass;
use Roots\Acorn\View\Composer;

use function get_parent_class;
use function is_dir;
use function is_file;
use function is_subclass_of;

/**
 * Base provider for a site plugin (`kleinweb-site-{key}`).
 *
 * Registered only on requests for that site, so anything it binds, hooks or
 * attaches stays on that site.  Adds helpers for the site-specific pieces a
 * Laravel package would wire up itself; WordPress hooks via `#[Action]` and
 * `#[Filter]` work as in any {@see ServiceProvider}.
 *
 * ```php
 * public function boot(): void
 * {
 *     parent::boot();
 *     $this->views(__DIR__ . '/../../resources/views');
 *     $this->composers([Composers\Header::class]);
 *     $this->webRoutes(__DIR__ . '/../../routes/web.php');
 * }
 * ```
 */
abstract class SiteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Nothing shared to boot; subclasses call their helpers here.
    }

    /**
     * Let a directory of Blade views override shared views by path.
     */
    protected function views(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        // Through the factory: Acorn binds `view.finder` unshared, so a
        // finder resolved here would not be the one the factory uses.
        $this->callAfterResolving('view', static function (mixed $view) use ($path): void {
            if ($view instanceof Factory) {
                $view->prependLocation($path);
            }
        });
    }

    /**
     * Attach view composers.
     *
     * A composer that extends a concrete shared composer replaces it: the
     * view factory resolves class-based composers through the container when
     * a view renders, so binding the shared class to the subclass is enough
     * and both never run.
     *
     * @param list<class-string<Composer>> $composers
     */
    protected function composers(array $composers): void
    {
        foreach ($composers as $composer) {
            $parent = get_parent_class($composer);

            if (
                $parent !== false
                && $parent !== Composer::class
                && is_subclass_of($parent, Composer::class)
                && !(new ReflectionClass($parent))->isAbstract()
            ) {
                $this->app->bind($parent, $composer);

                continue;
            }

            $this->callAfterResolving('view', static function (mixed $view) use ($composer): void {
                if ($view instanceof ViewFactory) {
                    $view->composer($composer::views(), $composer);
                }
            });
        }
    }

    /**
     * Load a routes file with the `web` middleware group.
     *
     * Skipped when routes are cached: `route:cache` is network-wide and
     * cannot hold one site's routes.
     */
    protected function webRoutes(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        $this->app->booted(static function () use ($path): void {
            Route::middleware('web')->group($path);
        });
    }
}
