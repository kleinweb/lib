<?php

/**
 * Plugin Name: Example
 * Description: The application: shared code for every site in the network.
 */

declare(strict_types=1);

// The network-wide application plugin and Acorn's base path (ACORN_BASEPATH
// in wp-config.php).  Nothing here knows about individual sites: site code
// lives in wp-content/plugins/kleinweb-site-{key}/ or in a theme, and
// kleinweb/lib's TenancyServiceProvider registers it for the requested site.

use Kleinweb\Lib\Application;

add_action('after_setup_theme', static function (): void {
    Application::configure()
        ->withProviders([
            App\Providers\AppServiceProvider::class,
        ])
        ->withRouting(web: __DIR__ . '/routes/web.php')
        ->boot();
}, priority: 0);
