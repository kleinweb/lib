<?php

// SPDX-FileCopyrightText: (C) 2024-2025 Temple University <kleinweb@temple.edu>
// SPDX-FileCopyrightText: (C) spatie
// SPDX-License-Identifier: GPL-3.0-or-later OR MIT

declare(strict_types=1);

namespace Kleinweb\Lib\Package\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use Kleinweb\Lib\Package\Package;
use Webmozart\Assert\Assert;

final class InstallCommand extends Command
{
    public ?Closure $startWith = null;

    /**
     * @var string[]
     */
    protected array $publishes = [];

    protected bool $askToRunMigrations = false;

    protected bool $copyServiceProviderInApp = false;

    public ?Closure $endWith = null;

    // FIXME: unused?
    // public $hidden = true;

    public function __construct(protected Package $package)
    {
        $this->signature = $package->shortName() . ':install';

        $this->description = 'Install ' . $package->name;


        parent::__construct();
    }

    /**
     * @throws BindingResolutionException
     * @throws FileNotFoundException
     */
    public function handle(): void
    {
        if ($this->startWith) {
            ($this->startWith)($this);
        }

        foreach ($this->publishes as $tag) {
            $name = str_replace('-', ' ', $tag);
            $this->comment("Publishing {$name}...");

            $this->callSilently('vendor:publish', [
                '--tag' => "{$this->package->shortName()}-{$tag}",
            ]);
        }

        if ($this->askToRunMigrations) {
            if ($this->confirm('Would you like to run the migrations now?')) {
                $this->comment('Running migrations...');

                $this->call('migrate');
            }
        }

        if ($this->copyServiceProviderInApp) {
            $this->comment('Publishing service provider...');
            $this->copyServiceProviderInApp();
        }

        $this->info("{$this->package->shortName()} has been installed!");

        if (!$this->endWith) {
            return;
        }

        ($this->endWith)($this);
    }

    /**
     * @param string ...$tag
     *
     * @return $this
     */
    public function publish(...$tag): self
    {
        $this->publishes = array_merge($this->publishes, $tag);

        return $this;
    }

    public function publishConfigFile(): self
    {
        return $this->publish('config');
    }

    public function publishAssets(): self
    {
        return $this->publish('assets');
    }

    public function publishInertiaComponents(): self
    {
        return $this->publish('inertia-components');
    }

    public function publishMigrations(): self
    {
        return $this->publish('migrations');
    }

    public function askToRunMigrations(): self
    {
        $this->askToRunMigrations = true;

        return $this;
    }

    public function copyAndRegisterServiceProviderInApp(): self
    {
        $this->copyServiceProviderInApp = true;

        return $this;
    }

    public function startWith(Closure $callable): self
    {
        $this->startWith = $callable;

        return $this;
    }

    public function endWith(Closure $callable): self
    {
        $this->endWith = $callable;

        return $this;
    }

    /**
     * @throws FileNotFoundException
     * @throws BindingResolutionException
     */
    protected function copyServiceProviderInApp(): self
    {
        $providerName = $this->package->publishableProviderName;

        if (! $providerName) {
            return $this;
        }

        $this->callSilent('vendor:publish', ['--tag' => $this->package->shortName() . '-provider']);

        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        if (
            (((int) app()->version()) < 11)
            || ! file_exists(base_path('bootstrap/providers.php'))
        ) {
            $appConfig = file_get_contents(config_path('app.php'));
        } else {
            $appConfig = file_get_contents(base_path('bootstrap/providers.php'));
        }

        if (!$appConfig) {
            throw new FileNotFoundException('Could not determine path to app configuration file');
        }

        $providerName = Str::replace('/', '\\', $providerName);
        Assert::string($providerName);

        $class = '\\Providers\\' . $providerName . '::class';

        if (Str::contains($appConfig, $namespace . $class)) {
            return $this;
        }

        if (
            (((int) app()->version()) < 11)
            || ! file_exists(base_path('bootstrap/providers.php'))
        ) {
            file_put_contents(config_path('app.php'), str_replace(
                "{$namespace}\\Providers\\BroadcastServiceProvider::class,",
                // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
                "{$namespace}\\Providers\\BroadcastServiceProvider::class," . PHP_EOL . "        {$namespace}{$class},",
                $appConfig,
            ));
        } else {
            file_put_contents(base_path('bootstrap/providers.php'), str_replace(
                "{$namespace}\\Providers\\AppServiceProvider::class,",
                // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
                "{$namespace}\\Providers\\AppServiceProvider::class," . PHP_EOL . "        {$namespace}{$class},",
                $appConfig,
            ));
        }

        $providerPath = app_path('Providers/' . $providerName . '.php');
        $providerContent = file_get_contents($providerPath);

        Assert::stringNotEmpty($providerContent);

        file_put_contents(
            $providerPath,
            str_replace(
                'namespace App\Providers;',
                "namespace {$namespace}\Providers;",
                $providerContent,
            ),
        );

        return $this;
    }
}
