<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Throwable;

use function basename;
use function class_exists;
use function count;
use function get_theme_root;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function preg_match;
use function sprintf;

use const GLOB_ONLYDIR;

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
    protected $description = 'Check site keys, site code, overlays and theme requirements';

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
        $this->checkThemeRequirements($files);
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

            $plugin = $manifest->pluginPath($key);
            if (!$key->isNamespaceable() || !is_file("{$plugin}/composer.json")) {
                continue;
            }

            foreach ($manifest->package($plugin, $key)['providers'] as $provider) {
                if (!class_exists($provider)) {
                    $this->problem('provider', $plugin, "provider {$provider} does not exist");
                }
            }
        }

        foreach (glob(get_theme_root() . '/*', GLOB_ONLYDIR) ?: [] as $theme) {
            foreach ($manifest->package($theme)['providers'] as $provider) {
                if (!class_exists($provider)) {
                    $this->problem('provider', $theme, "provider {$provider} does not exist");
                }
            }
        }
    }

    /**
     * Every theme's `require` is installed in the root project.
     */
    private function checkThemeRequirements(Filesystem $files): void
    {
        $parser = class_exists(VersionParser::class) ? new VersionParser() : null;

        foreach (glob(get_theme_root() . '/*/composer.json') ?: [] as $path) {
            try {
                $json = json_decode($files->get($path), true, flags: \JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $this->problem('theme', $path, 'composer.json is not valid JSON');

                continue;
            }

            $require = is_array($json) ? ($json['require'] ?? []) : [];
            foreach (is_array($require) ? $require : [] as $package => $constraint) {
                $platform = '/^(php|ext-|lib-|composer-)/';
                if (!is_string($package) || preg_match($platform, $package) === 1) {
                    continue;
                }

                if (!InstalledVersions::isInstalled($package)) {
                    $this->problem('theme', $path, "{$package} is not installed in the project");

                    continue;
                }

                if (
                    $parser !== null
                    && is_string($constraint)
                    && !InstalledVersions::satisfies($parser, $package, $constraint)
                ) {
                    $this->problem('theme', $path, sprintf(
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
