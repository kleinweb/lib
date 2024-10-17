<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-FileCopyrightText: (C) spatie
// SPDX-License-Identifier: GPL-3.0-or-later OR MIT


declare(strict_types=1);

namespace Kleinweb\Lib\Package;

use Illuminate\Support\Str;
use Kleinweb\Lib\Package\Commands\InstallCommand;

final class Package
{
    public string $name;

    /**
     * @var string[]
     */
    public array $configFileNames = [];

    public bool $hasViews = false;

    public bool $hasInertiaComponents = false;

    public ?string $viewNamespace = null;

    public bool $hasTranslations = false;

    public bool $hasAssets = false;

    public bool $runsMigrations = false;

    /**
     * @var string[]
     */
    public array $migrationFileNames = [];

    /**
     * @var string[]
     */
    public array $routeFileNames = [];

    /**
     * @var string[]
     */
    public array $commands = [];

    /**
     * @var \Kleinweb\Lib\Package\Commands\InstallCommand[]|class-string[]
     */
    public array $consoleCommands = [];

    /**
     * @var string[]
     */
    public array $viewComponents = [];

    /**
     * @var array<string, mixed>
     */
    public array $sharedViewData = [];

    /**
     * @var class-string[]
     */
    public array $viewComposers = [];

    public string $basePath;

    public ?string $publishableProviderName = null;

    public function name(string $name): Package
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string|string[]|null $configFileName
     *
     * @return $this
     */
    public function hasConfigFile(string|array|null $configFileName = null): Package
    {
        $configFileName ??= $this->shortName();

        if (! is_array($configFileName)) {
            $configFileName = [$configFileName];
        }

        $this->configFileNames = $configFileName;

        return $this;
    }

    public function publishesServiceProvider(string $providerName): Package
    {
        $this->publishableProviderName = $providerName;

        return $this;
    }

    public function hasInstallCommand(callable $callable): Package
    {
        $installCommand = new InstallCommand($this);

        $callable($installCommand);

        $this->consoleCommands[] = $installCommand;

        return $this;
    }

    public function shortName(): string
    {
        return Str::after($this->name, 'laravel-');
    }

    public function hasViews(?string $namespace = null): Package
    {
        $this->hasViews = true;

        $this->viewNamespace = $namespace;

        return $this;
    }

    public function hasInertiaComponents(?string $namespace = null): Package
    {
        $this->hasInertiaComponents = true;

        $this->viewNamespace = $namespace;

        return $this;
    }

    /**
     * @param class-string<\Illuminate\View\Component> $viewComponentName
     *
     * @return $this
     */
    public function hasViewComponent(string $prefix, string $viewComponentName): Package
    {
        $this->viewComponents[$viewComponentName] = $prefix;

        return $this;
    }

    /**
     * @param class-string<\Illuminate\View\Component> ...$viewComponentNames
     *
     * @return $this
     */
    public function hasViewComponents(string $prefix, ...$viewComponentNames): Package
    {
        foreach ($viewComponentNames as $componentName) {
            $this->viewComponents[$componentName] = $prefix;
        }

        return $this;
    }

    public function sharesDataWithAllViews(string $name, mixed $value): Package
    {
        $this->sharedViewData[$name] = $value;

        return $this;
    }

    /**
     * @param string|string[] $view
     * @param class-string    $viewComposer
     *
     * @return $this
     */
    public function hasViewComposer(string|array $view, string $viewComposer): Package
    {
        if (! is_array($view)) {
            $view = [$view];
        }

        foreach ($view as $viewName) {
            $this->viewComposers[$viewName] = $viewComposer;
        }

        return $this;
    }

    public function hasTranslations(): Package
    {
        $this->hasTranslations = true;

        return $this;
    }

    public function hasAssets(): Package
    {
        $this->hasAssets = true;

        return $this;
    }

    public function runsMigrations(bool $runsMigrations = true): Package
    {
        $this->runsMigrations = $runsMigrations;

        return $this;
    }

    public function hasMigration(string $migrationFileName): Package
    {
        $this->migrationFileNames[] = $migrationFileName;

        return $this;
    }

    /**
     * @param string ...$migrationFileNames
     *
     * @return $this
     */
    public function hasMigrations(...$migrationFileNames): Package
    {
        $this->migrationFileNames = array_merge(
            $this->migrationFileNames,
            collect($migrationFileNames)->flatten()->toArray(),
        );

        return $this;
    }

    public function hasCommand(string $commandClassName): Package
    {
        $this->commands[] = $commandClassName;

        return $this;
    }

    /**
     * @param class-string ...$commandClassNames
     *
     * @return $this
     */
    public function hasCommands(...$commandClassNames): Package
    {
        $this->commands = array_merge($this->commands, collect($commandClassNames)->flatten()->toArray());

        return $this;
    }

    /**
     * @param class-string $commandClassName
     *
     * @return $this
     */
    public function hasConsoleCommand(string $commandClassName): Package
    {
        $this->consoleCommands[] = $commandClassName;

        return $this;
    }

    /**
     * @param class-string ...$commandClassNames
     *
     * @return $this
     */
    public function hasConsoleCommands(...$commandClassNames): Package
    {
        $this->consoleCommands = array_merge(
            $this->consoleCommands,
            collect($commandClassNames)->flatten()->toArray(),
        );

        return $this;
    }

    public function hasRoute(string $routeFileName): Package
    {
        $this->routeFileNames[] = $routeFileName;

        return $this;
    }

    /**
     * @param string ...$routeFileNames
     *
     * @return $this
     */
    public function hasRoutes(...$routeFileNames): Package
    {
        $this->routeFileNames = array_merge($this->routeFileNames, collect($routeFileNames)->flatten()->toArray());

        return $this;
    }

    public function basePath(?string $directory = null): string
    {
        if ($directory === null) {
            return $this->basePath;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($directory, DIRECTORY_SEPARATOR);
    }

    public function viewNamespace(): string
    {
        return $this->viewNamespace ?? $this->shortName();
    }

    public function setBasePath(string $path): Package
    {
        $this->basePath = $path;

        return $this;
    }
}
