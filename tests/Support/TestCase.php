<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Starts every test from a fresh fake WordPress.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WordPress::reset();
    }

    /**
     * The example project's files, which double as fixtures.
     */
    protected static function example(string $path = ''): string
    {
        return \constant('KLEINWEB_EXAMPLE') . ($path !== '' ? "/{$path}" : '');
    }

    /**
     * The example application's shared config file.
     *
     * @return array<array-key, mixed>
     */
    protected static function exampleConfig(string $name): array
    {
        $config = require self::example("wp-content/mu-plugins/example/config/{$name}.php");
        self::assertIsArray($config);

        return $config;
    }
}
