<?php

declare(strict_types=1);

namespace Kleinweb\Lib;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Roots\Acorn\Application as RootsApplication;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\PackageManifest;
use Throwable;

final class Application extends RootsApplication
{
    /**
     * Skip booting service provider and log error.
     *
     * @param ServiceProvider|string $provider
     */
    protected function skipProvider($provider, Throwable $e): ServiceProvider
    {
        $providerName = is_object($provider) ? $provider::class : $provider;

        if (! $e instanceof SkipProviderException) {
            $error = $e::class;
            $message = [
                BindingResolutionException::class => "Skipping provider [{$providerName}] because it requires a dependency that cannot be found.",
            ][$error] ?? "Skipping provider [{$providerName}] because it encountered an error [{$error}]: {$e->getMessage()}";

            $e = new SkipProviderException($message, 0, $e);
        }

        $packages = $this->make(PackageManifest::class);
        if ($packages instanceof PackageManifest) {
            $e->setPackage($packages->getPackage($providerName));
        }

        $this->make(ExceptionHandler::class)->report($e);

        if ($this->environment('development', 'testing', 'local') && ! $this->runningInConsole()) {
            $this->booted(static fn () => throw $e);
        }

        return is_object($provider) ? $provider : new class ($this) extends ServiceProvider {};
    }
}
