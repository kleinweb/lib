<?php

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

use Illuminate\Contracts\Container\BindingResolutionException;
use Kleinweb\Lib\Hooks\Attributes\Action;
use Kleinweb\Lib\Support\ServiceProvider;

/**
 * Register site-specific post types and taxonomies from `config/content-types.php`.
 */
final class ContentTypesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(ContentTypeRegistrar::class);
    }

    /**
     * Register all content types late on init after other plugins' registrations.
     *
     * @throws BindingResolutionException
     */
    #[Action('init', 100)]
    public function afterInitRegisterAll(): void
    {
        $this->app->make(ContentTypeRegistrar::class)->registerAll();
    }
}
