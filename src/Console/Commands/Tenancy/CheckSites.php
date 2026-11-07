<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\Exceptions\MultisiteOnly;
use Kleinweb\Lib\Tenancy\Site;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SitePluginLoader;
use Kleinweb\Lib\Tenancy\SiteRepository;

/**
 * Report multisite configuration problems.
 */
final class CheckSites extends Command
{
    protected $signature = 'site:check';

    protected $description = 'Check Acorn multitenancy status';

    /** @var list<array{string, string, string}> */
    private array $problems = [];

    /**
     * @throws MultisiteOnly
     */
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
        $this->checkRoutes($sites, $files, $app);

        if ($this->problems === []) {
            $this->components->info('No problems found.');

            return self::SUCCESS;
        }

        $this->table(['Check', 'Subject', 'Problem'], $this->problems);
        $this->components->info(sprintf('There are %d problems.', count($this->problems)));

        return self::FAILURE;
    }

    /**
     * @throws MultisiteOnly
     */
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
                $site->storedKey() === null => 'no key.  Run `site:assign-keys`.',
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
     * @throws MultisiteOnly
     */
    private function checkSitePlugins(SiteRepository $sites, Filesystem $files): void
    {
        if (!SitePluginLoader::isRegistered()) {
            $this->problem('plugin', 'mu-plugins', 'SitePluginLoader::register() is not called '
            . 'from an mu-plugin, so site-plugins\' own plugin files are not loaded');
        }

        foreach (SitePlugin::networkActive() as $plugin) {
            $this->problem(
                'plugin',
                $plugin,
                'is network-activated. '
            . 'Site-plugins load automatically on their own site. '
            . 'Please deactivate the site-plugin at the network-level.',
            );
        }

        $keys = $sites->all()
            ->map(static fn (Site $site): ?string => $site->key()?->value)
            ->filter()
            ->values()
        ;

        $pluginsRoot = constant('WP_PLUGIN_DIR');
        $pluginDirs = is_dir($pluginsRoot) ? $files->directories($pluginsRoot) : [];
        foreach ($pluginDirs as $dir) {
            $name = basename($dir);

            if (!str_starts_with($name, SitePlugin::PREFIX)) {
                continue;
            }

            $key = SitePlugin::keyFor($name);
            if (($key === null) || !$keys->contains($key->value)) {
                $this->problem('plugin', $dir, 'no site has a matching key');
            }
        }

        foreach ($sites->all() as $site) {
            $active = $site->run(static fn (): array => SitePluginLoader::withoutOwnPlugin(
                SitePlugin::active(...),
            ));

            foreach ($active as $plugin) {
                $this->problem('plugin', $plugin, sprintf(
                    'is activated or loaded by other code on site %d. '
                    . 'Site-plugins load automatically on their own site. '
                    . 'Please deactivate the site-plugin on site %d.',
                    $site->id(),
                    $site->id(),
                ));
            }
        }
    }

    /**
     * @throws MultisiteOnly
     */
    private function checkProviders(SiteRepository $sites, SiteManifest $manifest): void
    {
        foreach ($manifest->packageDirs($sites) as $dir) {
            foreach ($manifest->providers($dir) as $provider) {
                if (!class_exists($provider)) {
                    $this->problem('provider', $dir, "provider {$provider} does not exist}");
                }
            }
        }
    }

    /**
     * @throws MultisiteOnly
     */
    private function checkAutoload(SiteRepository $sites, SiteManifest $manifest): void
    {
        $known = [];
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $known += $loader->getPrefixesPsr4();
        }

        foreach ($manifest->packageDirs($sites) as $dir) {
            $composer = $manifest->composerJson($dir);
            if ($composer === null) {
                // TODO: is this message always accurate?
                $this->problem('autoload', $dir, 'composer.json is invalid JSON');

                continue;
            }

            $autoload = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];

            if (($autoload['files'] ?? []) !== []) {
                $this->problem('autoload', $dir, '`autoload.files` run on every site. Use PSR-4 or classmap instead.');
            }

            $psr4 = is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [];
            foreach (array_keys($psr4) as $prefix) {
                if (!array_key_exists($prefix, $known)) {
                    $this->problem('autoload', $dir, sprintf(
                        '%s is not autoloaded. '
                    . 'Include this site\'s composer.json with '
                    . '<wikimedia/composer-merge-plugin> and run `composer update`.',
                        $prefix,
                    ));
                }
            }
        }
    }

    /**
     * @throws MultisiteOnly
     */
    private function checkRequirements(SiteRepository $sites, SiteManifest $manifest): void
    {
        $parser = class_exists(VersionParser::class) ? new VersionParser() : null;

        foreach ($manifest->packageDirs($sites) as $dir) {
            $json = $manifest->composerJson($dir) ?? [];
            $require = $json['require'] ?? [];

            foreach (is_array($require) ? $require : [] as $package => $constraint) {
                $platform = '/^(php|ext-|lib-|composer-)/';

                if (!is_string($package) || (preg_match($platform, $package) === 1)) {
                    continue;
                }

                if (!InstalledVersions::isInstalled($package)) {
                    $this->problem('require', $dir, "package {$package} is not installed");

                    continue;
                }

                if (
                    ($parser !== null)
                    && is_string($constraint)
                    && !InstalledVersions::satisfies($parser, $package, $constraint)
                ) {
                    $this->problem('require', $dir, sprintf(
                        '%s %s does not satisfy constraint %s',
                        $package,
                        InstalledVersions::getPrettyVersion($package) ?? '?',
                        $constraint,
                    ));
                }
            }
        }
    }

    private function checkRoutes(SiteRepository $sites, Filesystem $files, Application $app): void
    {
        if (!$app->routesAreCached()) {
            return;
        }

        foreach ($sites->all() as $site) {
            $key = $site->key();

            if (($key !== null) && ($files->glob(SitePlugin::path($key) . '/routes/*.php') !== [])) {
                $this->problem(
                    'routes',
                    SitePlugin::path($key),
                    'routes are cached, so this site-plugin\'s routes are not loaded',
                );
            }
        }
    }

    private function problem(string $check, string $subject, string $problem): void
    {
        $this->problems[] = [$check, $subject, $problem];
    }
}
