<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;

/**
 * Invoke an Acorn command once per site, each in its own process.
 *
 * A fresh process per site allows for loading each site's providers.
 * Conversely, a same-process invocation with {@see switch_to_blog()}
 * would only switch WordPress data and configuration.
 */
final class RunForSites extends Command
{
    // use Inter
}
