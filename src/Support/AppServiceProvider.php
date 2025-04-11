<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Kleinweb\Lib\Console\Commands\Attachment\DeleteDead as DeleteDeadAttachments;
use Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains;
use Kleinweb\Lib\Support\ServiceProvider as ServiceProviderBase;

final class AppServiceProvider extends ServiceProviderBase
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            DeleteDeadAttachments::class,
            DemapDomains::class,
        ]);
    }
}
