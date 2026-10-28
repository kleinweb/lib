<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Kleinweb\Lib\Tenancy\Queue\RunsOnSiteWorker;
use Kleinweb\Lib\Tenancy\Queue\ShouldRunOnSiteWorker;

use function get_current_blog_id;

/**
 * A job that needs its site's bindings.
 */
final class RebuildSiteIndex implements ShouldRunOnSiteWorker
{
    use Dispatchable;
    use Queueable;
    use RunsOnSiteWorker;

    /** @var list<int> blogs the job ran on */
    public static array $ranOn = [];

    public function handle(): void
    {
        self::$ranOn[] = get_current_blog_id();
    }
}
