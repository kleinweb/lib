<?php

/**
 * Plugin Name: Application
 * Description: Boots the shared Acorn application for every site.
 */

declare(strict_types=1);

// Shared application only.  Nothing here knows about individual sites: site
// code lives in wp-content/plugins/kleinweb-site-{key}/ or in a theme, and
// kleinweb/lib's TenancyServiceProvider registers it for the requested site.

use Kleinweb\Lib\Application;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

add_action('after_setup_theme', static function (): void {
    // Pass the base path: Acorn otherwise infers it from the first of these
    // that exists, and a theme with a composer.json would win:
    // get_theme_file_path('composer.json'), get_theme_file_path('app').
    Application::configure(dirname(__DIR__, 2))
        ->withProviders([
            App\Providers\AppServiceProvider::class,
        ])
        ->withRouting(web: dirname(__DIR__, 2) . '/routes/web.php')
        ->boot();
}, 0);
