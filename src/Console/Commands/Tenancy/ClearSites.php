<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;

final class ClearSites extends Command
{
    protected $signature = 'site:clear';

    protected $description = 'Remove the site discovery cache';

    public function handle(SiteManifest $manifest): int
    {
        if (!$manifest->clear()) {
            $this->components->error('Could not clear site discovery cache.');

            return self::FAILURE;
        }

        $this->components->info('Site discovery cache cleared.');

        return self::SUCCESS;
    }
}
