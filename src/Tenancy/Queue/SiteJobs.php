<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Queue;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Queue;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\WrongSiteWorker;
use Kleinweb\Lib\Tenancy\SiteContext;
use Kleinweb\Lib\Tenancy\SitePlugin;

use function get_current_blog_id;
use function is_a;
use function is_string;
use function spl_object_id;

/**
 * Queue behaviour on top of Acorn's.
 *
 * Acorn adds the dispatching blog's ID to each job and switches to that blog
 * while it runs.  This adds:
 *
 * - `siteKey` in the payload, the dispatching blog's key, so a job can be
 *   matched to a site across environments.
 * - A check before a {@see ShouldRunOnSiteWorker} job runs: on a worker
 *   started for another site, or for no keyed site, the job is failed with
 *   {@see WrongSiteWorker} instead of running with the wrong bindings.  On
 *   the sync driver the exception also reaches the code that dispatched it.
 * - Unwinding after every job to the switch depth it started at.  Acorn
 *   restores once; a job that leaves its own switches unrestored would
 *   otherwise leave the worker on another blog for every later job.
 *
 * The listeners must be added before Acorn's own, which Acorn adds when it
 * boots, so the depth is recorded before Acorn switches.
 */
final class SiteJobs
{
    /** @var array<int, int> job object ID => switch depth before the job */
    private array $depths = [];

    public function __construct(private readonly SiteContext $context) {}

    public function register(Dispatcher $events): void
    {
        Queue::createPayloadUsing(static fn (): array => [
            'siteKey' => SitePlugin::keyForBlog(get_current_blog_id())?->value,
        ]);

        $events->listen(JobProcessing::class, $this->processing(...));
        $events->listen(JobAttempted::class, $this->attempted(...));
    }

    public function processing(JobProcessing $event): void
    {
        $this->depths[spl_object_id($event->job)] = Site::switchDepth();

        $payload = $event->job->payload();
        $class = $payload['data']['commandName'] ?? null;
        if (!is_string($class) || !is_a($class, ShouldRunOnSiteWorker::class, true)) {
            return;
        }

        $jobSite = self::siteKey($payload);
        $workerSite = $this->context->initialized()?->key()?->value;

        if ($jobSite !== null && $jobSite === $workerSite) {
            return;
        }

        $exception = WrongSiteWorker::for($class, $jobSite, $workerSite);
        $event->job->fail($exception);

        // A worker skips a job failed before it runs; the sync driver does
        // not check, so stop it there, which also reports to the dispatcher.
        if ($event->job instanceof SyncJob) {
            throw $exception;
        }
    }

    public function attempted(JobAttempted $event): void
    {
        $id = spl_object_id($event->job);
        $depth = $this->depths[$id] ?? null;
        unset($this->depths[$id]);

        if ($depth !== null) {
            Site::unwindTo($depth);
        }
    }

    /**
     * The dispatching site's key recorded in a job payload.
     *
     * @param array<array-key, mixed> $payload
     */
    public static function siteKey(array $payload): ?string
    {
        $key = $payload['siteKey'] ?? null;

        return is_string($key) ? $key : null;
    }
}
