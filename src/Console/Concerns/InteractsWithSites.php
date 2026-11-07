<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Concerns;

use Illuminate\Console\Command;
use Closure;

/**
 * Iterate over sites from a console command.
 *
 * @phpstan-require-extends Command
 */
trait InteractsWithSites
{
    /**
     * Invoke a callback for each selected site.
     *
     * A callback that throws, returns `false`, or returns a non-zero integer
     * counts as a failure for that site.  By default, a failure will be
     * reported, the previous site will be restored, and the loop continues.
     *
     * With `$contentTypes`, each site's post types and taxonomies are
     * registered while its callback runs in {@see }
     */
    protected function forEachSite(Closure $callback, bool $contentTypes = true): int {}
}
