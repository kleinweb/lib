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
// Site plugins (kleinweb-site-*) are never loaded here: each is activated in
// WordPress on its own site, which is what scopes it to that site.

require __DIR__ . '/example/example.php';
