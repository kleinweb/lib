<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Discovery;

use Composer\Autoload\ClassLoader;

use function array_key_exists;
use function reset;

/**
 * Registers PSR-4 prefixes with Composer's autoloader at runtime.
 *
 * Used for code Composer does not already autoload: a site plugin, or a theme
 * kept in the project repository without the merge plugin.
 */
final class Psr4Autoloader
{
    /**
     * Register each prefix the autoloader does not already know.
     *
     * Prefixes Composer already maps (for example through
     * `wikimedia/composer-merge-plugin`) are left alone, so the optimized
     * autoloader stays authoritative.
     *
     * @param array<string, list<string>> $map prefix => absolute directories
     */
    public static function register(array $map): void
    {
        $loader = self::loader();
        if ($loader === null || $map === []) {
            return;
        }

        $known = $loader->getPrefixesPsr4();
        foreach ($map as $prefix => $directories) {
            if (!array_key_exists($prefix, $known)) {
                $loader->addPsr4($prefix, $directories);
            }
        }
    }

    private static function loader(): ?ClassLoader
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);

        return ($loader instanceof ClassLoader) ? $loader : null;
    }
}
