<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

use function switch_to_blog;

/**
 * A network job that forgets to restore its own switches.
 */
final class LeavesSwitched implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        switch_to_blog(3);
        switch_to_blog(5);
    }
}
