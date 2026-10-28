<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Queue;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\Queue;
use Kleinweb\Lib\Tenancy\SiteQueue;

use function is_int;

/**
 * Pushes the job onto the queue of the site it is dispatched from,
 * `site-{key}`, whatever queue was set on it.
 *
 * ```php
 * final class RebuildIndex implements ShouldRunOnSiteWorker
 * {
 *     use Queueable;
 *     use RunsOnSiteWorker;
 * }
 *
 * RebuildIndex::dispatch();   // from the news site, or switched to it
 * ```
 *
 * processed by `wp --url=<news url> acorn queue:work --queue=site-news`.
 *
 * Laravel's dispatcher calls `queue()` when a job defines it.  Dispatching
 * from a blog without a site key throws.  Use it with Laravel's `Queueable`,
 * whose delay it honours.
 *
 * @phpstan-require-implements ShouldRunOnSiteWorker
 */
trait RunsOnSiteWorker
{
    public function queue(Queue $queue, mixed $command): mixed
    {
        $name = SiteQueue::current();
        $delay = $this->siteWorkerDelay();

        return ($delay !== null)
            ? $queue->later($delay, $command, '', $name)
            : $queue->push($command, '', $name);
    }

    private function siteWorkerDelay(): DateInterval|DateTimeInterface|int|null
    {
        $delay = $this->delay;

        return match (true) {
            is_int($delay),
            $delay instanceof DateInterval,
            $delay instanceof DateTimeInterface => $delay,
            default => null,
        };
    }
}
