<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * Writes vendor/pest-plugins.json, the list of Pest plugins Pest loads at
 * startup (including its own printer, coverage and parallel plugins).
 *
 * Pest's Composer plugin normally writes it, but it cannot run here:
 * alleyinteractive/composer-wordpress-autoloader (required by
 * alleyinteractive/wp-bulk-task) loads the project's vendor/autoload.php
 * into Composer's own process, and Pest's plugin then mixes Composer's
 * bundled Symfony Console with the project's and dies.  So
 * `pestphp/pest-plugin` is disabled in `allow-plugins`, and this runs as a
 * separate process from `post-autoload-dump`, doing what Pest's
 * `pest:dump-plugins` does: collect `extra.pest.plugins` from every
 * installed package and the root package.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$vendor = "{$root}/vendor";

$read = static function (string $path): array {
    $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    return is_array($json) ? $json : [];
};

$installed = $read("{$vendor}/composer/installed.json");
$packages = [...($installed['packages'] ?? []), $read("{$root}/composer.json")];

$plugins = [];
foreach ($packages as $package) {
    foreach ($package['extra']['pest']['plugins'] ?? [] as $plugin) {
        if (is_string($plugin)) {
            $plugins[] = $plugin;
        }
    }
}

file_put_contents("{$vendor}/pest-plugins.json", json_encode($plugins, JSON_PRETTY_PRINT));
