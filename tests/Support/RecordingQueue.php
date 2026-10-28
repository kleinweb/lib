<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

use Illuminate\Queue\NullQueue;

/**
 * Records what is pushed instead of running it.
 */
final class RecordingQueue extends NullQueue
{
    /** @var list<array{job: mixed, queue: ?string, delay: mixed}> */
    public array $pushed = [];

    /**
     * @param string|null $queue
     */
    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter -- Queue contract.
    public function push($job, $data = '', $queue = null): mixed
    {
        $this->pushed[] = ['job' => $job, 'queue' => $queue, 'delay' => null];

        return null;
    }

    /**
     * @param string|null $queue
     */
    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter -- Queue contract.
    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        $this->pushed[] = ['job' => $job, 'queue' => $queue, 'delay' => $delay];

        return null;
    }
}
