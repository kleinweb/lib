<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Discovery;

use ReflectionClass;
use Symfony\Component\Finder\Finder;

use function class_exists;
use function is_dir;
use function is_subclass_of;
use function sort;
use function str_replace;
use function substr;

/**
 * Finds concrete classes in a PSR-4 directory.
 */
final class ClassFinder
{
    /**
     * Concrete subclasses of `$base` under `$directory`, sorted by name.
     *
     * The directory must already be autoloadable under `$namespace`.
     *
     * @template T of object
     *
     * @param class-string<T> $base
     *
     * @return list<class-string<T>>
     */
    public static function find(string $directory, string $namespace, string $base): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $classes = [];
        $finder = Finder::create()->files()->name('*.php')->in($directory);

        foreach ($finder as $file) {
            $relative = substr($file->getRelativePathname(), 0, -4);
            $class = $namespace . str_replace('/', '\\', $relative);

            if (!class_exists($class) || !is_subclass_of($class, $base)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }
}
