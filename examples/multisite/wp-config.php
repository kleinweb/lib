<?php

/*
 * Excerpt: only what the example depends on.  See
 * kleinweb/project-template's wp-config.php for the full file.
 */

declare(strict_types=1);

define('PRJ_ROOT_DIR', __DIR__);
define('APP_WEBROOT_DIR', PRJ_ROOT_DIR);

// Acorn's base path is the application mu-plugin, not the project root.
// Set explicitly: Acorn would otherwise infer it from the active theme's
// composer.json, which every theme here has.
define('ACORN_BASEPATH', PRJ_ROOT_DIR . '/wp-content/mu-plugins/example');

require_once PRJ_ROOT_DIR . '/vendor/autoload.php';

define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', true);
define('DOMAIN_CURRENT_SITE', 'example.test');
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

if (!defined('ABSPATH')) {
    define('ABSPATH', APP_WEBROOT_DIR . '/');
}

require_once ABSPATH . 'wp-settings.php';
