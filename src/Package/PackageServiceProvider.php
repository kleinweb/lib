<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-FileCopyrightText: (C) spatie
// SPDX-License-Identifier: GPL-3.0-or-later OR MIT


declare(strict_types=1);

namespace Kleinweb\Lib\Package;

use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Kleinweb\Lib\Support\ServiceProvider;
use ReflectionClass;
use Kleinweb\Lib\Package\Exceptions\InvalidPackage;
use ReflectionException;
use Webmozart\Assert\Assert;

abstract class PackageServiceProvider extends ServiceProvider
{
    protected Package $package;

    abstract public function configurePackage(Package $package): void;

    /**
     * @throws InvalidPackage
     * @throws ReflectionException
     */
    public function register(): void
    {
        parent::register();

        $this->registeringPackage();

        $this->package = PackageServiceProvider::newPackage();

        $this->package->setBasePath($this->getPackageBaseDir());

        $this->configurePackage($this->package);

        if (!$this->package->name) {
            throw InvalidPackage::nameIsRequired();
        }

        foreach ($this->package->configFileNames as $configFileName) {
            $this->mergeConfigFrom($this->package->basePath("/../config/{$configFileName}.php"), $configFileName);
        }

        $this->packageRegistered();
    }

    public static function newPackage(): Package
    {
        return new Package();
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        parent::boot();

        $this->bootingPackage();

        $langPath = 'vendor/' . $this->package->shortName();
        $langPath = (function_exists('lang_path'))
            ? lang_path($langPath)
            : resource_path('lang/' . $langPath);

        if ($this->app->runningInConsole()) {
            foreach ($this->package->configFileNames as $configFileName) {
                $this->publishes([
                    $this->package->basePath("/../config/{$configFileName}.php") => config_path("{$configFileName}.php"),
                ], "{$this->package->shortName()}-config");
            }

            if ($this->package->hasViews) {
                $this->publishes([
                    $this->package->basePath('/../resources/views') => base_path("resources/views/vendor/{$this->packageView($this->package->viewNamespace)}"),
                ], "{$this->packageView($this->package->viewNamespace)}-views");
            }

            if ($this->package->hasInertiaComponents) {
                /** @phpstan-ignore-next-line parameter.type (From upstream.  Needs refactor to address underlying issue.) */
                $packageDirectoryName = Str::of($this->packageView($this->package->viewNamespace))->studly()->remove('-')->value();

                $this->publishes([
                    $this->package->basePath('/../resources/js/Pages') => base_path("resources/js/Pages/{$packageDirectoryName}"),
                ], "{$this->packageView($this->package->viewNamespace)}-inertia-components");
            }

            $now = Carbon::now();
            foreach ($this->package->migrationFileNames as $migrationFileName) {
                $filePath = $this->package->basePath("/../database/migrations/{$migrationFileName}.php");
                if (! file_exists($filePath)) {
                    // Support for the .stub file extension
                    $filePath .= '.stub';
                }

                $this->publishes([
                    $filePath => static::generateMigrationName(
                        $migrationFileName,
                        $now->addSecond(),
                    ), ], "{$this->package->shortName()}-migrations");

                if (!$this->package->runsMigrations) {
                    continue;
                }

                $this->loadMigrationsFrom($filePath);
            }

            if ($this->package->hasTranslations) {
                $this->publishes([
                    $this->package->basePath('/../resources/lang') => $langPath,
                ], "{$this->package->shortName()}-translations");
            }

            if ($this->package->hasAssets) {
                $this->publishes([
                    $this->package->basePath('/../resources/dist') => public_path("vendor/{$this->package->shortName()}"),
                ], "{$this->package->shortName()}-assets");
            }
        }

        if ($this->package->commands) {
            $this->commands($this->package->commands);
        }

        if ($this->package->consoleCommands && $this->app->runningInConsole()) {
            $this->commands($this->package->consoleCommands);
        }

        if ($this->package->hasTranslations) {
            $this->loadTranslationsFrom(
                $this->package->basePath('/../resources/lang/'),
                $this->package->shortName(),
            );

            $this->loadJsonTranslationsFrom($this->package->basePath('/../resources/lang/'));

            $this->loadJsonTranslationsFrom($langPath);
        }

        if ($this->package->hasViews) {
            $this->loadViewsFrom($this->package->basePath('/../resources/views'), $this->package->viewNamespace());
        }

        foreach ($this->package->viewComponents as $componentClass => $prefix) {
            $this->loadViewComponentsAs($prefix, [$componentClass]);
        }

        if (count($this->package->viewComponents)) {
            $this->publishes([
                $this->package->basePath('/Components') => base_path("app/View/Components/vendor/{$this->package->shortName()}"),
            ], "{$this->package->name}-components");
        }

        if ($this->package->publishableProviderName) {
            $this->publishes([
                $this->package->basePath("/../resources/stubs/{$this->package->publishableProviderName}.php.stub") => base_path("app/Providers/{$this->package->publishableProviderName}.php"),
            ], "{$this->package->shortName()}-provider");
        }


        foreach ($this->package->routeFileNames as $routeFileName) {
            $this->loadRoutesFrom("{$this->package->basePath('/../routes/')}{$routeFileName}.php");
        }

        foreach ($this->package->sharedViewData as $name => $value) {
            View::share($name, $value);
        }

        foreach ($this->package->viewComposers as $viewName => $viewComposer) {
            View::composer($viewName, $viewComposer);
        }

        $this->packageBooted();
    }

    /**
     * @throws BindingResolutionException
     */
    public static function generateMigrationName(string $migrationFileName, Carbon $now): string
    {
        $migrationsPath = 'migrations/' . dirname($migrationFileName) . '/';
        $migrationFileName = basename($migrationFileName);

        $len = strlen($migrationFileName) + 4;

        if (Str::contains($migrationFileName, '/')) {
            $migrationsPath .= Str::of($migrationFileName)->beforeLast('/')->finish('/');
            $migrationFileName = Str::of($migrationFileName)->afterLast('/');
        }

        $filenames = glob(database_path("{$migrationsPath}*.php")) ?: [];
        foreach ($filenames as $filename) {
            if (substr($filename, -$len) === $migrationFileName . '.php') {
                return $filename;
            }
        }

        return database_path($migrationsPath . $now->format('Y_m_d_His') . '_' . Str::of($migrationFileName)->snake()->finish('.php'));
    }

    public function registeringPackage(): void
    {
        // TODO: implement
    }

    public function packageRegistered(): void
    {
        // TODO: implement
    }

    public function bootingPackage(): void
    {
        // TODO: implement
    }

    public function packageBooted(): void
    {
        // TODO: implement
    }

    /**
     * @throws ReflectionException
     */
    protected function getPackageBaseDir(): string
    {
        $reflector = new ReflectionClass($this::class);
        $filename = $reflector->getFileName();
        Assert::stringNotEmpty($filename);

        return dirname($filename);
    }

    public function packageView(?string $namespace): ?string
    {
        return is_null($namespace) ? $this->package->shortName() : $this->package->viewNamespace;
    }
}
