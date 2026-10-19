<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Console\Application as Artisan;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use ReflectionClass;
use Roots\Acorn\View\Composer;

use function array_fill_keys;
use function array_keys;
use function array_reverse;
use function array_unique;
use function array_values;
use function class_exists;
use function get_option;
use function get_parent_class;
use function get_site_option;
use function get_stylesheet_directory;
use function get_template_directory;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function is_subclass_of;
use function str_starts_with;

/**
 * Registers the request site's code with the application.
 *
 * Providers are registered least specific first, so a later binding wins:
 * the site's own providers (`app/Sites/{Key}/Providers`, its site plugin, and
 * `providers` in `config/sites/{key}/app.php`), then the parent theme's, then
 * the child theme's.  Called once, from an application `booting` callback, so
 * every configured provider has already registered.
 */
final class SiteLoader
{
    public function __construct(
        private readonly Application $app,
        private readonly SiteManifest $manifest,
    ) {}

    public function load(Site $site): void
    {
        $key = $site->key();
        if ($key === null) {
            return;
        }

        $code = $key->isNamespaceable()
            ? $this->manifest->site($key)
            : ['providers' => [], 'composers' => [], 'commands' => []];

        $providers = $code['providers'];

        if ($this->pluginIsActive($key->value)) {
            $plugin = $this->manifest->pluginPath($key);
            $providers = [...$providers, ...$this->manifest->providers($plugin)];
        }

        $extra = $this->app->make('config')->get("sites.{$key->value}.app.providers", []);
        if (is_array($extra)) {
            $providers = [...$providers, ...array_values(\array_filter($extra, is_string(...)))];
        }

        foreach ($this->themeDirectories() as $theme) {
            $providers = [...$providers, ...$this->manifest->providers($theme)];
        }

        // Keep the last occurrence of a repeated provider, preserving the
        // least-to-most-specific order.
        $providers = array_reverse(array_unique(array_reverse($providers)));

        foreach (array_values($providers) as $provider) {
            $this->registerProvider($provider);
        }

        $this->attachComposers($code['composers']);
        $this->registerCommands($code['commands']);
        $this->registerViews($key->value);
        $this->registerRoutes($key->value);
    }

    /**
     * Parent theme first, then the child theme if one is active.
     *
     * @return list<string>
     */
    private function themeDirectories(): array
    {
        $template = get_template_directory();
        $stylesheet = get_stylesheet_directory();

        return ($template === $stylesheet) ? [$template] : [$template, $stylesheet];
    }

    /**
     * Whether `plugins/{key}/` is active on the current site or network-wide.
     */
    private function pluginIsActive(string $key): bool
    {
        $prefix = "{$key}/";
        $active = get_option('active_plugins', []);
        $network = get_site_option('active_sitewide_plugins', []);

        $plugins = [
            ...(is_array($active) ? $active : []),
            ...(is_array($network) ? array_keys($network) : []),
        ];

        foreach ($plugins as $plugin) {
            if (is_string($plugin) && str_starts_with($plugin, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register a provider, honouring deferral.
     *
     * Deferred providers are not in Laravel's cached provider manifest, so
     * their services are mapped here instead.
     */
    private function registerProvider(string $class): void
    {
        if (!class_exists($class) || !is_subclass_of($class, ServiceProvider::class)) {
            // Acorn reports and skips missing providers.
            $this->app->register($class);

            return;
        }

        $provider = new $class($this->app);

        if ($provider->isDeferred()) {
            $this->app->addDeferredServices(array_fill_keys($provider->provides(), $class));

            return;
        }

        $this->app->register($provider);
    }

    /**
     * Attach site view composers.
     *
     * A composer that extends a concrete shared composer replaces it: the view
     * factory resolves composers through the container at render time, so
     * binding the shared class to the subclass is enough.
     *
     * @param list<string> $composers
     */
    private function attachComposers(array $composers): void
    {
        if ($composers === []) {
            return;
        }

        $view = $this->app->make('view');

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

            if (is_subclass_of($composer, Composer::class)) {
                $view->composer($composer::views(), $composer);
            }
        }
    }

    /**
     * @param list<string> $commands
     */
    private function registerCommands(array $commands): void
    {
        if ($commands === [] || !$this->app->runningInConsole()) {
            return;
        }

        Artisan::starting(static function (Artisan $artisan) use ($commands): void {
            $artisan->resolveCommands($commands);
        });
    }

    /**
     * Let `resources/views/sites/{key}` override shared views by path.
     */
    private function registerViews(string $key): void
    {
        $path = $this->app->resourcePath("views/sites/{$key}");
        if (!is_dir($path)) {
            return;
        }

        $prepend = static function (FileViewFinder $finder) use ($path): void {
            $finder->prependLocation($path);
        };
        $this->app->afterResolving('view.finder', $prepend);

        if (!$this->app->resolved('view.finder')) {
            return;
        }

        $finder = $this->app->make('view.finder');
        if ($finder instanceof FileViewFinder) {
            $finder->prependLocation($path);
        }
    }

    /**
     * Load `routes/sites/{key}.php` with the `web` middleware group.
     *
     * Skipped when routes are cached: `route:cache` is network-wide and cannot
     * hold per-site routes.  `site:check` reports this.
     */
    private function registerRoutes(string $key): void
    {
        $path = $this->app->basePath("routes/sites/{$key}.php");
        if (!is_file($path) || $this->app->routesAreCached()) {
            return;
        }

        $this->app->booted(static function () use ($path): void {
            Route::middleware('web')->group($path);
        });
    }
}
