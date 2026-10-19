<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;

final class ClearSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the site discovery cache';

    public function handle(SiteManifest $manifest): int
    {
        if (!$manifest->clear()) {
            $this->components->error('Could not remove the site discovery cache.');

            return self::FAILURE;
        }

        $this->components->info('Site discovery cache cleared.');

        return self::SUCCESS;
    }
}
