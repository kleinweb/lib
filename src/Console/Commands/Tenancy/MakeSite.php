<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Support\Url;
use Kleinweb\Lib\Tenancy\Domain;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use Kleinweb\Lib\Tenancy\Exceptions\MultisiteOnly;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SiteRepository;
use League\Uri\Uri;
use Webmozart\Assert\Assert;

final class MakeSite extends Command
{
    // FIXME: help text
    protected $signature = 'make:site
        {key : Site key}
        {--url= : TKTKTKTK}
        {--force : Overwrite existing files}';

    protected $description = 'Create a new site plugin';

    public function handle(Filesystem $files, SiteRepository $sites): int
    {
        $value = $this->argument('key');

        try {
            // TODO: why not SiteKey::fromString() ?
            $key = SiteKey::from(is_string($value) ? $value : '');
            $studly = $key->studly();
        } catch (InvalidSiteKey $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        // Check the target site before writing anything.
        $url = $this->option('url');
        $site = null;
        if (is_string($url) && ($url !== '')) {
            // TODO: arguments order seems less than intuitive
            $site = $this->siteToAssign($sites, $url, $key);
            if ($site === false) {
                return self::FAILURE;
            }
        }

        $namespace = rtrim(SitePlugin::namespace($key), '\\');
        $provider = "{$studly}ServiceProvider";
        $plugin = SitePlugin::path($key);

        $targets = [
            "{$plugin}/" . SitePlugin::directory($key) . '.php' => $this->pluginStub($key),
            "{$plugin}/composer.json" => $this->composerStub($key, $namespace, $provider),
            "{$plugin}/src/Providers/{$provider}.php" => $this->providerStub(
                $namespace,
                $provider,
                $key,
            ),
        ];

        if (!$files->isDirectory("{$plugin}/config")) {
            $targets["{$plugin}/config/.gitkeep"] = '';
        }

        foreach ($targets as $path => $contents) {
            if ($files->exists($path) && !$this->option('force')) {
                $this->components->warn("Skipped [{$path}]: already exists.");

                continue;
            }

            $files->ensureDirectoryExists($files->dirname($path));
            $files->put($path, $contents);

            $this->components->info("Created [{$path}].");
        }

        $this->components->bulletList([
            'Run `composer update` so <wikimedia/composer-merge-plugin> detects the '
            . 'plugin\'s composer.json',
            sprintf(
                'Nothing to activate.  The site-plugin loads automatically on the site with the key "%s".',
                $key->value,
            ),
        ]);

        if ($site instanceof Site) {
            return $this->call('site:assign-keys', [
                '--id' => (string) $site->id(),
                '--key' => $key->value,
            ]);
        }

        if ($site === null && ($sites->find($key) === null)) {
            $this->components->warn(sprintf(
                'No site yet has the key "%s". Set it with '
                . '`make:site %s --url={site-url}` or '
                . '`site:assign-keys --id={site-id}` --key=%s`.',
                $key->value,
                $key->value,
                $key->value,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * FIXME: avoid polymorphic return
     *
     * @throws MultisiteOnly
     */
    private function siteToAssign(SiteRepository $sites, string $url, SiteKey $key): Site|bool
    {
        $target = self::domainOf($url);
        $domainPredicate = static fn (Site $site): bool => (string) $site->domain() === (string) $target;
        $site = $sites->all()->first($domainPredicate);

        if ($site === null) {
            $this->components->error("No site has the URL <{$url}>");

            // FIXME: avoid polymorphic return
            return false;
        }

        $stored = $site->storedKey();
        if ($stored === $key->value) {
            return true;
        }

        $siteTitle = $this->siteTitle($key);

        if ($stored !== null) {
            $this->components->error(sprintf(
                'Site %s [%d] already has the key "%s". '
                // TODO: the original recommendation did not include an `--id` option here.  make sure the recommendation actually works.
                . 'Change it with `site:assign-keys --id={site-id} --force`.',
                $siteTitle,
                $site->id(),
                $stored,
            ));

            // FIXME: avoid polymorphic return
            return false;
        }

        $owner = $sites->find($key);
        if ($owner !== null) {
            $this->components->error(sprintf(
                // FIXME: needs $owner->title()
                // 'The key "%s" is already used by site %s [%d].',
                'The key "%s" is already used by site %d.',
                $key->value,
                $owner->id(),
            ));

            return false;
        }

        return $site;
    }

    /**
     * TODO: use {@see Uri} or {@see Url}
     * FIXME: docstring
     */
    private static function domainOf(string $url): Domain
    {
        $parts = parse_url(str_contains($url, '://') ? $url : "https://{$url}");
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        $path = is_array($parts) ? ($parts['path'] ?? '/') : '/';

        return new Domain($host, $path);
    }

    private function pluginStub(SiteKey $key): string
    {
        // TODO: standardize elsewhere (abstract Plugin class?)
        $projectTitle = defined('KLEINWEB_PROJECT_TITLE')
            ? constant('KLEINWEB_PROJECT_TITLE')
            : '';
        Assert::string($projectTitle);

        $title = $this->siteTitle($key);
        $pluginName = $projectTitle
            ? sprintf('%s Site-specific Features: %s', $projectTitle, $title)
            : sprintf('%s: Site-specific Features', $title);
        $description = $this->sitePluginDescription($key);

        return <<<PHP
<?php

declare(strict_types=1);

/**
 * Plugin Name: {$pluginName}
 * Description: {$description}
 * Author:      Klein College of Media and Communication <kleinweb@temple.edu>
 * Author URI:  https://klein.temple.edu
 * License:     GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// This site-specific plugin is loaded automatically and exclusively on the
// {$projectTitle} [{$key->value}] site. Never activate this plugin directly.
//
// Service providers must be declared in composer.json
// under the '.extras.acorn.providers' property.

PHP;

    }

    private function composerStub(SiteKey $key, string $namespace, string $provider): string
    {
        $composer = [
            'name' => 'kleinweb/' . SitePlugin::directory($key),
            'description' => $this->sitePluginDescription($key),
            'type' => 'wordpress-plugin',
            'license' => 'GPL-3.0-or-later',
            'autoload' => [
                'psr-4' => ["{$namespace}\\" => 'src/'],
            ],
            'extra' => [
                'acorn' => [
                    'providers' => ["{$namespace}\\Providers\\{$provider}"],
                ],
            ],
        ];

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        return json_encode($composer, $flags) . "\n";
    }

    private function providerStub(string $namespace, string $class, SiteKey $key): string
    {
        return <<<PHP
<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace {$namespace}\\Providers;

use Kleinweb\\Lib\\Tenancy\\SiteServiceProvider;

/**
 * Site-specific provider for the "{$key->value}" site.
 *
 * This site-specific provider will only be registered on requests for
 * the "{$key->value}" site.
 */
final class {$class} extends SiteServiceProvider
{
    /**
     * Site-specific bindings, which may override shared bindings on this site.
     *
     * @var array<class-string, class-string>
     */
    public array \$bindings = [];

    public function boot(): void
    {
        parent::boot();

        // \$this->views(__DIR__ . '/../../resources/views');
        // \$this->webRoutes(__DIR__ . '/../../routes/web.php');

        // TODO ...
    }
}

PHP;

    }

    private function siteTitle(SiteKey $key): string
    {
        return ucwords(str_replace('-', ' ', $key->value));
    }

    /**
     * FIXME: move to {@see SitePlugin::description()}
     */
    private function sitePluginDescription(SiteKey $key): string
    {
        $title = $this->siteTitle($key);

        return "Site-specific code and configuration for the \"{$title}\" site";
    }
}
