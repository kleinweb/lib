<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SiteRepository;

use function config_path;
use function is_string;
use function rtrim;
use function sprintf;

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
    protected $description = 'Create the code and config skeleton for a site';

    public function handle(Filesystem $files, SiteManifest $manifest, SiteRepository $sites): int
    {
        $value = $this->argument('key');

        try {
            $key = SiteKey::from(is_string($value) ? $value : '');
            $studly = $key->studly();
        } catch (InvalidSiteKey $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $namespace = rtrim($manifest->siteNamespace($key), '\\');
        $provider = "{$studly}ServiceProvider";

        $providerPath = $manifest->sitePath($key) . "/Providers/{$provider}.php";
        $targets = [
            $providerPath => $this->providerStub($namespace, $provider, $key),
            config_path("sites/{$key->value}/app.php") => $this->configStub(),
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

    private function providerStub(string $namespace, string $class, SiteKey $key): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace}\\Providers;

            use Illuminate\\Support\\ServiceProvider;

            /**
             * Registered only on the "{$key->value}" site.
             */
            final class {$class} extends ServiceProvider
            {
                /**
                 * Bindings that replace shared ones on this site.
                 *
                 * @var array<class-string, class-string>
                 */
                public array \$bindings = [];

                public function register(): void
                {
                    //
                }

                public function boot(): void
                {
                    //
                }
            }

            PHP;
    }

    private function configStub(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            /*
             * Site overlay for config/app.php.  Other files in this directory
             * overlay the config file of the same name, e.g. mail.php.
             */
            return [
                // Extra providers registered only on this site.
                'providers' => [],
            ];

            PHP;
    }
}
