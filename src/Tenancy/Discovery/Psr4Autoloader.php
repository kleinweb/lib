<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Discovery;

use Composer\Autoload\ClassLoader;

use function array_key_exists;
use function is_file;
use function reset;
use function spl_autoload_register;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function uksort;

/**
 * Makes PSR-4 prefixes loadable at runtime.
 *
 * Used for code Composer does not already autoload: a site plugin, or a theme
 * kept in the project repository without the merge plugin.
 *
 * Prefixes are resolved by this class's own autoloader rather than added to
 * Composer's, because Composer ignores runtime PSR-4 additions when the
 * autoloader was dumped with `--classmap-authoritative`.
 */
final class Psr4Autoloader
{
    /** @var array<string, list<string>> prefix => directories */
    private static array $map = [];

    private static bool $registered = false;

    /**
     * Register each prefix Composer does not already know.
     *
     * Prefixes Composer maps (for example through
     * `wikimedia/composer-merge-plugin`) are left to Composer.
     *
     * @param array<string, list<string>> $map prefix => absolute directories
     */
    public static function register(array $map): void
    {
        if ($map === []) {
            return;
        }

        $known = self::composer()?->getPrefixesPsr4() ?? [];
        $added = false;

        foreach ($map as $prefix => $directories) {
            if (array_key_exists($prefix, $known)) {
                continue;
            }

            self::$map[$prefix] = [...(self::$map[$prefix] ?? []), ...$directories];
            $added = true;
        }

        if (!$added) {
            return;
        }

        // Longest prefix first, as PSR-4 requires.
        uksort(self::$map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        if (self::$registered) {
            return;
        }

        self::$registered = true;
        spl_autoload_register(static function (string $class): void {
            self::load($class);
        });
    }

    /**
     * Load a class from a registered prefix.  Returns whether it was found.
     */
    public static function load(string $class): bool
    {
        foreach (self::$map as $prefix => $directories) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            foreach ($directories as $directory) {
                $file = "{$directory}/{$relative}";
                if (is_file($file)) {
                    require_once $file;

                    return true;
                }
            }
        }

        return false;
    }

    private static function composer(): ?ClassLoader
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);

        return ($loader instanceof ClassLoader) ? $loader : null;
    }
}
