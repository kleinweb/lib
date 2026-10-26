<?php

declare(strict_types=1);

namespace Kleinweb\Themes\Base;

use Illuminate\Support\ServiceProvider;

/**
 * Registered while this theme is active, as parent or as the theme itself,
 * after the site plugin's providers and before a child theme's.
 *
 * Uses Illuminate only: whatever registers it supplies the container.
 */
final class ThemeServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        Layout::class => Layout::class,
    ];

    public function boot(): void
    {
        // `base::` views, plus the application's own (and a site plugin's
        // overrides) without a namespace.
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'base');

        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        register_nav_menus(['primary' => esc_html__('Primary', 'kleinweb-base')]);
    }
}
