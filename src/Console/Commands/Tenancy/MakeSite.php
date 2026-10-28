<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SiteRepository;

use function is_string;
use function json_encode;
use function rtrim;
use function sprintf;
use function str_replace;
use function ucwords;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Create a site plugin: `wp-content/plugins/kleinweb-site-{key}/`.
 */
final class MakeSite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:site
        {key : The site key}
        {--force : Overwrite files that already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a site plugin with a provider and a config directory';

    public function handle(Filesystem $files, SiteRepository $sites): int
    {
        $value = $this->argument('key');

        try {
            $key = SiteKey::from(is_string($value) ? $value : '');
            $studly = $key->studly();
        } catch (InvalidSiteKey $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
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
            "{$plugin}/config/.gitkeep" => '',
        ];

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
            'Run `composer update` so wikimedia/composer-merge-plugin picks up the '
            . 'plugin\'s composer.json (include wp-content/plugins/kleinweb-site-*/composer.json).',
            sprintf(
                'Nothing to activate: it loads automatically on the site with the key "%s" '
                . '(SitePluginLoader::register() in an mu-plugin).',
                $key->value,
            ),
        ]);

        if ($sites->find($key) === null) {
            $this->components->warn(sprintf(
                'No site has the key "%s" yet; set it with '
                . '`site:assign-keys --id=<blog id> --key=%s`.',
                $key->value,
                $key->value,
            ));
        }

        return self::SUCCESS;
    }

    private function pluginStub(SiteKey $key): string
    {
        $title = ucwords(str_replace('-', ' ', $key->value));

        return <<<PHP
            <?php

            /**
             * Plugin Name: {$title} site
             * Description: Code and configuration specific to the "{$key->value}" site.
             */

            declare(strict_types=1);

            // Loaded automatically on the "{$key->value}" site only; never activate it.
            // Providers are declared in composer.json; config overlays live in config/.

            PHP;
    }

    private function composerStub(SiteKey $key, string $namespace, string $provider): string
    {
        $composer = [
            'name' => 'kleinweb/' . SitePlugin::directory($key),
            'description' => "Code and configuration specific to the \"{$key->value}\" site.",
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

        return json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
    }

    private function providerStub(string $namespace, string $class, SiteKey $key): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace}\\Providers;

            use Kleinweb\\Lib\\Tenancy\\SiteServiceProvider;

            /**
             * Registered only on requests for the "{$key->value}" site.
             */
            final class {$class} extends SiteServiceProvider
            {
                /**
                 * Bindings that replace shared ones on this site.
                 *
                 * @var array<class-string, class-string>
                 */
                public array \$bindings = [];

                public function boot(): void
                {
                    parent::boot();

                    // \$this->views(__DIR__ . '/../../resources/views');
                    // \$this->webRoutes(__DIR__ . '/../../routes/web.php');
                }
            }

            PHP;
    }
}
