<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SiteRepository;

final class CacheSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache discovered site, site-plugin and theme code for the network';

    public function handle(SiteManifest $manifest, SiteRepository $sites): int
    {
        $sites->refresh();
        $path = $manifest->write($sites);

        $this->components->info("Site discovery cached in [{$path}].");

        return self::SUCCESS;
    }
}
