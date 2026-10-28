<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;

use function array_fill_keys;
use function array_reverse;
use function array_unique;
use function array_values;
use function class_exists;
use function get_stylesheet_directory;
use function get_template_directory;
use function is_subclass_of;

/**
 * Registers the request site's providers with the application.
 *
 * The application holds no site-specific code.  Site code lives in the
 * site's plugin (`kleinweb-site-{key}`) or in its theme, and declares its
 * providers in its `composer.json` (`extra.acorn.providers`).  Only this
 * site's code is used:
 *
 * 1. the site plugin named for this site's key, if it exists;
 * 2. the active parent theme;
 * 3. the active child theme.
 *
 * Later providers register later, so their bindings win.  Called once, from
 * an application `booting` callback, so every configured provider has
 * already registered.
 */
final class SiteLoader
{
    public function __construct(
        private readonly Application $app,
        private readonly SiteManifest $manifest,
    ) {}

    public function load(Site $site): void
    {
        $providers = [];

        $key = $site->key();
        if ($key !== null && SitePlugin::exists($key)) {
            $providers = $this->manifest->providers(SitePlugin::path($key));
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
}
