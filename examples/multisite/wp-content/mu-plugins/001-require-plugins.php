<?php

/**
 * Plugin Name: Require plugins
 * Description: Loads code-enabled plugins, including the application.
 */

declare(strict_types=1);

// WordPress only loads PHP files directly in mu-plugins/.  Projects from
// kleinweb/project-template load the application and other code-enabled
// plugins here with alleyinteractive/wp-plugin-loader; a plain require is
// enough for the example.
//
// Never list a site plugin (kleinweb-site-*) in wp-plugin-loader: it makes
// plugins active on every site.  SitePluginLoader loads each site's own
// plugin on that site only.

Kleinweb\Lib\Tenancy\SitePluginLoader::register();

require __DIR__ . '/example/example.php';
