<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

// The example project doubles as the fixture: its site plugins and config are
// what the tests read, so the example cannot drift from what the code does.
define('KLEINWEB_EXAMPLE', dirname(__DIR__) . '/examples/multisite');
define('WP_PLUGIN_DIR', KLEINWEB_EXAMPLE . '/wp-content/plugins');
define('WP_CONTENT_DIR', KLEINWEB_EXAMPLE . '/wp-content');

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/functions.php';

Kleinweb\Lib\Tests\Support\WordPress::reset();
