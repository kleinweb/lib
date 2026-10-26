<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SiteRepository;

use function array_key_exists;
use function array_keys;
use function basename;
use function class_exists;
use function count;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function preg_match;
use function reset;
use function sprintf;
use function str_starts_with;

/**
 * Report multisite configuration problems; exits non-zero if any are found.
 */
final class CheckSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check site keys, site plugins, autoloading and requirements';

    /** @var list<array{string, string, string}> */
    private array $problems = [];

    public function handle(
        SiteRepository $sites,
        SiteManifest $manifest,
        Filesystem $files,
        Application $app,
    ): int {
        $sites->refresh();

        $this->checkKeys($sites);
        $this->checkSitePlugins($sites, $files);
        $this->checkProviders($sites, $manifest);
        $this->checkAutoload($sites, $manifest);
        $this->checkRequirements($sites, $manifest);
        $this->checkPackages();
        $this->checkRoutes($sites, $files, $app);

        if ($this->problems === []) {
            $this->components->info('No problems found.');

            return self::SUCCESS;
        }

        $this->table(['Check', 'Subject', 'Problem'], $this->problems);
        $this->components->error(sprintf('%d problem(s) found.', count($this->problems)));

        return self::FAILURE;
    }

    private function checkKeys(SiteRepository $sites): void
    {
        $counts = $sites->all()
            ->map(static fn (Site $site): ?string => $site->key()?->value)
            ->filter()
            ->countBy()
            ->all()
        ;

        foreach ($sites->all() as $site) {
            $subject = sprintf('site %d <%s>', $site->id(), $site->url());
            $key = $site->key();

            $problem = match (true) {
                $site->storedKey() === null => 'no key; run site:assign-keys',
                $key === null => sprintf('invalid key "%s"', $site->storedKey()),
                ($counts[$key->value] ?? 0) > 1 => "key \"{$key->value}\" is used by another site",
                $key->isReserved() => sprintf('key "%s" is reserved', $key->value),
                default => null,
            };

            if ($problem !== null) {
                $this->problem('key', $subject, $problem);
            }
        }
    }

    /**
     * Site plugins: each matches a site, is active only where it belongs,
     * and is active wherever it carries config.
     */
    private function checkSitePlugins(SiteRepository $sites, Filesystem $files): void
    {
        $keys = $sites->all()
            ->map(static fn (Site $site): ?string => $site->key()?->value)
            ->filter()
            ->values()
        ;

        $pluginRoot = \constant('WP_PLUGIN_DIR');
        foreach (is_dir($pluginRoot) ? $files->directories($pluginRoot) : [] as $directory) {
            $name = basename($directory);
            if (!str_starts_with($name, SitePlugin::PREFIX)) {
                continue;
            }

            $key = SitePlugin::keyFor($name);
            if ($key === null || !$keys->contains($key->value)) {
                $this->problem('plugin', $directory, 'no site has a matching key');
            }
        }

        foreach ($sites->all() as $site) {
            $key = $site->key();
            $active = $site->run(static fn (): array => SitePlugin::activeDirectories());

            foreach ($active as $directory) {
                $pluginKey = SitePlugin::keyFor($directory);
                if ($pluginKey !== null && !$pluginKey->equals((string) $key)) {
                    $this->problem('plugin', $directory, sprintf(
                        'is active on site %d, which is not its site; it is ignored there',
                        $site->id(),
                    ));
                }
            }

            if ($key === null) {
                continue;
            }

            if ($site->run(static fn (): bool => SitePlugin::isNetworkActive($key))) {
                $this->problem('plugin', SitePlugin::path($key), 'is network-activated; '
                    . 'WordPress then loads its PHP on every site; activate it on its site only');
            }

            $isActive = in_array(SitePlugin::directory($key), $active, true);
            if (!$isActive && is_dir(SitePlugin::configPath($key))) {
                $this->problem('plugin', SitePlugin::path($key), sprintf(
                    'has config but is not active on site %d, so none of it applies',
                    $site->id(),
                ));
            }
        }
    }

    /**
     * Providers declared by themes and site plugins exist.
     */
    private function checkProviders(SiteRepository $sites, SiteManifest $manifest): void
    {
        foreach ($manifest->packageDirectories($sites) as $directory) {
            foreach ($manifest->providers($directory) as $provider) {
                if (!class_exists($provider)) {
                    $this->problem('provider', $directory, "provider {$provider} does not exist");
                }
            }
        }
    }

    /**
     * Themes and site plugins are autoloaded by Composer, without side effects.
     *
     * Their classes load on every site, which is harmless; per-site behaviour
     * comes from registering providers.  Composer's `autoload.files`, though,
     * run on every request for every site, so they are not allowed.
     */
    private function checkAutoload(SiteRepository $sites, SiteManifest $manifest): void
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        $known = ($loader instanceof ClassLoader) ? $loader->getPrefixesPsr4() : [];

        foreach ($manifest->packageDirectories($sites) as $directory) {
            $composer = $manifest->composerJson($directory);
            if ($composer === null) {
                $this->problem('autoload', $directory, 'composer.json is not valid JSON');

                continue;
            }

            $autoload = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];

            if (($autoload['files'] ?? []) !== []) {
                $this->problem(
                    'autoload',
                    $directory,
                    'autoload.files run on every site; use psr-4 or classmap',
                );
            }

            $psr4 = is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [];
            foreach (array_keys($psr4) as $prefix) {
                if (!array_key_exists($prefix, $known)) {
                    $this->problem('autoload', $directory, sprintf(
                        '%s is not autoloaded; include this composer.json with '
                        . 'wikimedia/composer-merge-plugin and run composer update',
                        $prefix,
                    ));
                }
            }
        }
    }

    /**
     * Every theme's and site plugin's `require` is installed in the project.
     */
    private function checkRequirements(SiteRepository $sites, SiteManifest $manifest): void
    {
        $parser = class_exists(VersionParser::class) ? new VersionParser() : null;

        foreach ($manifest->packageDirectories($sites) as $directory) {
            $json = $manifest->composerJson($directory) ?? [];
            $require = $json['require'] ?? [];

            foreach (is_array($require) ? $require : [] as $package => $constraint) {
                $platform = '/^(php|ext-|lib-|composer-)/';
                if (!is_string($package) || preg_match($platform, $package) === 1) {
                    continue;
                }

                if (!InstalledVersions::isInstalled($package)) {
                    $this->problem('require', $directory, "{$package} is not installed");

                    continue;
                }

                if (
                    $parser !== null
                    && is_string($constraint)
                    && !InstalledVersions::satisfies($parser, $package, $constraint)
                ) {
                    $this->problem('require', $directory, sprintf(
                        '%s %s does not satisfy %s',
                        $package,
                        InstalledVersions::getPrettyVersion($package) ?? '?',
                        $constraint,
                    ));
                }
            }
        }
    }

    /**
     * Packages this library replaces, which would register things twice.
     */
    private function checkPackages(): void
    {
        if (InstalledVersions::isInstalled('roots/acorn-post-types')) {
            $this->problem(
                'package',
                'roots/acorn-post-types',
                'kleinweb/lib registers post types per site; remove this package',
            );
        }
    }

    /**
     * `route:cache` is network-wide, so site plugins' routes are not loaded.
     */
    private function checkRoutes(SiteRepository $sites, Filesystem $files, Application $app): void
    {
        if (!$app->routesAreCached()) {
            return;
        }

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key !== null && $files->glob(SitePlugin::path($key) . '/routes/*.php') !== []) {
                $this->problem(
                    'routes',
                    SitePlugin::path($key),
                    'routes are cached, so this site plugin\'s routes are not loaded',
                );
            }
        }
    }

    private function problem(string $check, string $subject, string $problem): void
    {
        $this->problems[] = [$check, $subject, $problem];
    }
}
