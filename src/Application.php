<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib;

use Illuminate\Support\Facades\Config;
use Roots\Acorn\Application as RootsApplication;
use Illuminate\Foundation\PackageManifest as FoundationPackageManifest;
use Roots\Acorn\Filesystem\Filesystem;
use Roots\Acorn\PackageManifest;

final class Application extends RootsApplication
{
    public function webRoot(): string
    {
        return Config::string('app.web_root') ?: $this->appPath;
    }

    /**
     * Register the package manifest.
     *
     * @see https://github.com/roots/acorn/issues/410
     *
     * @return void
     */
    protected function registerPackageManifest()
    {
        $this->singleton(FoundationPackageManifest::class, function () {
            $files = new Filesystem();

            /** @var array<int, string> $activePlugins */
            $activePlugins = get_option('active_plugins');

            $composerPaths = collect($activePlugins)
                ->map(static fn ($plugin) => WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($plugin))
                /* @phpstan-ignore argument.type (Preserve upstream usage) */
                ->merge([
                    $this->basePath(),
                    // HACK: Override upstream assumption about distance between
                    // `composer.json` and content directory to match our
                    // project structure.
                    ABSPATH,
                    get_template_directory(),
                    get_stylesheet_directory(),
                ])
                ->map(static fn ($path) => rtrim($files->normalizePath($path), '/'))
                ->unique()
                ->filter(
                    static fn ($path) => @$files->isFile("{$path}/vendor/composer/installed.json")
                        && @$files->isFile("{$path}/composer.json"),
                )->all();

            return new PackageManifest(
                $files,
                $composerPaths,
                $this->getCachedPackagesPath(),
            );
        });

        $this->alias(FoundationPackageManifest::class, PackageManifest::class);
    }
}
