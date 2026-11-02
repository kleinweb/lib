<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Tenancy\SiteRepository;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function constant;
use function is_array;
use function sys_get_temp_dir;
use function uniqid;

/**
 * A path in the example project, which doubles as the test fixture.
 */
function example(string $path = ''): string
{
    return constant('KLEINWEB_EXAMPLE') . ($path !== '' ? "/{$path}" : '');
}

/**
 * One of the example application's shared config files.
 *
 * @return array<array-key, mixed>
 */
function exampleConfig(string $name): array
{
    return requireArray(example("wp-content/mu-plugins/example/config/{$name}.php"));
}

/**
 * A site plugin's config file from the example.
 *
 * @return array<array-key, mixed>
 */
function sitePluginConfig(string $key, string $name): array
{
    return requireArray(example("wp-content/plugins/kleinweb-site-{$key}/config/{$name}.php"));
}

/**
 * @return array<array-key, mixed>
 */
function requireArray(string $file): array
{
    $value = require $file;
    if (!is_array($value)) {
        throw new RuntimeException("{$file} does not return an array.");
    }

    return $value;
}

/**
 * A directory under the system temp dir, unique to the call.
 */
function tempPath(string $prefix): string
{
    return sys_get_temp_dir() . '/' . uniqid("kleinweb-{$prefix}-", true);
}

/**
 * A Laravel application with what the site commands resolve.
 */
function testApplication(?string $basePath = null): Application
{
    $app = new Application($basePath ?? sys_get_temp_dir());
    $app->instance('files', new Filesystem());
    $app->instance(SiteRepository::class, new SiteRepository());

    return $app;
}

/**
 * Run a console command, returning its exit code and output.
 *
 * @param array<string, mixed> $input
 *
 * @return array{int, string}
 */
function runCommand(Command $command, Application $app, array $input = []): array
{
    $command->setLaravel($app);
    $output = new BufferedOutput();
    $code = $command->run(new ArrayInput($input), $output);

    return [$code, $output->fetch()];
}
