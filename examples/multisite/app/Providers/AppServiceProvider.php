<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\ContentReport;
use Kleinweb\Lib\Support\ServiceProvider;

/**
 * Shared by every site.  Nothing site-specific belongs here.
 */
final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([ContentReport::class]);
    }
}
