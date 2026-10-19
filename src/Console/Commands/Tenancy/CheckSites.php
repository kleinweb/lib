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
    protected $description = 'Check site keys, site code, overlays, autoloading and requirements';

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
        $this->checkOrphans($sites, $files, $app);
        $this->checkProviders($sites, $manifest, $app);
        $this->checkAutoload($sites, $manifest);
        $this->checkRequirements($sites, $manifest);
        $this->checkRoutes($files, $app);

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
     * Site code and config overlays whose key matches no site.
     */
    private function checkOrphans(SiteRepository $sites, Filesystem $files, Application $app): void
    {
        $keys = $sites->all()
            ->map(static fn (Site $site): ?string => $site->key()?->value)
            ->filter()
            ->values()
        ;

        $studly = $sites->all()
            ->map(static function (Site $site): ?string {
                $key = $site->key();

                return ($key !== null && $key->isNamespaceable()) ? $key->studly() : null;
            })
            ->filter()
            ->values()
            ->all()
        ;

        $sitesPath = $app->path('Sites');
        foreach (is_dir($sitesPath) ? $files->directories($sitesPath) : [] as $directory) {
            if (!in_array(basename($directory), $studly, true)) {
                $this->problem('orphan', $directory, 'no site has a matching key');
            }
        }

        $configPath = $app->configPath('sites');
        foreach (is_dir($configPath) ? $files->directories($configPath) : [] as $directory) {
            if (!$keys->contains(basename($directory))) {
                $this->problem('orphan', $directory, 'no site has a matching key');
            }
        }
    }

    /**
     * Providers named in config or in theme and site-plugin manifests exist.
     */
    private function checkProviders(
        SiteRepository $sites,
        SiteManifest $manifest,
        Application $app,
    ): void {
        $config = $app->make('config');

        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key === null) {
                continue;
            }

            $extra = $config->get("sites.{$key->value}.app.providers", []);
            foreach (is_array($extra) ? $extra : [] as $provider) {
                if (!is_string($provider) || !class_exists($provider)) {
                    $this->problem('provider', "config/sites/{$key->value}/app.php", sprintf(
                        'provider %s does not exist',
                        is_string($provider) ? $provider : '(not a class name)',
                    ));
                }
            }
        }

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

    private function checkRoutes(Filesystem $files, Application $app): void
    {
        if (!$app->routesAreCached()) {
            return;
        }

        if ($files->glob($app->basePath('routes/sites/*.php')) !== []) {
            $this->problem(
                'routes',
                'routes/sites/',
                'routes are cached, so per-site routes are not loaded',
            );
        }
    }

    private function problem(string $check, string $subject, string $problem): void
    {
        $this->problems[] = [$check, $subject, $problem];
    }
}
