<?php

declare(strict_types=1);

namespace Kleinweb\Sitekit\Support;

use Illuminate\Support\ServiceProvider as ServiceProviderBase;
use Kleinweb\Sitekit\Hooks\Traits\Hookable;

/**
 * Service provider base class providing framework-specific features.
 */
abstract class ServiceProvider extends ServiceProviderBase
{
    use Hookable;

    public function register(): void
    {
        parent::register();

        $this->booted(fn () => $this->registerHooks());
    }
}
