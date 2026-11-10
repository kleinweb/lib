<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SiteRepository;

final class CacheSites extends Command
{
    protected $signature = 'site:cache';

    protected $description = 'Cache-discovered site, site-plugin, and theme code';

    public function handle(SiteManifest $manifest, SiteRepository $sites): int
    {
        $sites->refresh();

        $path = $manifest->write($sites);

        $this->components->info("Site discovery cached in [{$path}].");

        return self::SUCCESS;
    }
}
