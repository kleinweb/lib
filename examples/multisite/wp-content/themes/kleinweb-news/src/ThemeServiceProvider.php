<?php

declare(strict_types=1);

namespace Kleinweb\Themes\News;

use Illuminate\Support\ServiceProvider;
use Kleinweb\Themes\Base\Layout;

/**
 * Registered after the parent theme's provider, so its bindings win.
 */
final class ThemeServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        Layout::class => WideLayout::class,
    ];
}
