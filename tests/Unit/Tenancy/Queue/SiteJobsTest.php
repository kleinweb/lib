<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * Blogs: 1 `main`, 2 `news`, 3 no key, 5 `events`.
 */

declare(strict_types=1);

use Illuminate\Bus\Dispatcher as Bus;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as BusContract;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;
use Illuminate\Queue\SyncQueue;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use Kleinweb\Lib\Tenancy\Exceptions\WrongSiteWorker;
use Kleinweb\Lib\Tenancy\Queue\SiteJobs;
use Kleinweb\Lib\Tenancy\SiteContext;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\Jobs\LeavesSwitched;
use Kleinweb\Lib\Tests\Support\Jobs\RebuildSiteIndex;
use Kleinweb\Lib\Tests\Support\PayloadJob;
use Kleinweb\Lib\Tests\Support\RecordingQueue;
use Kleinweb\Lib\Tests\Support\WordPress;

/**
 * A job as a worker receives it.
 *
 * @param class-string $class
 */
function queuedJob(string $siteKey, string $class): PayloadJob
{
    return new PayloadJob(['siteKey' => $siteKey, 'data' => ['commandName' => $class]]);
}

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(3);
    WordPress::addSite(5, 'events');
    RebuildSiteIndex::$ranOn = [];

    $this->container = new Container();
    Container::setInstance($this->container);
    $this->events = new Dispatcher($this->container);
    $this->container->instance('events', $this->events);
    $this->container->instance(DispatcherContract::class, $this->events);
    $this->container->instance(ContainerContract::class, $this->container);

    // The listeners, in a process initialized for a blog.
    $this->siteJobs = function (int $workerSite): SiteJobs {
        $context = new SiteContext(
            new SiteRepository(),
            $this->events,
            [],
            [],
            static function (): void {},
        );
        $context->initialize(Site::find($workerSite), $workerSite);
        $jobs = new SiteJobs($context);
        $jobs->register($this->events);

        return $jobs;
    };

    // A bus on the sync driver, in a process initialized for a blog.
    $this->syncBus = function (int $workerSite): Bus {
        ($this->siteJobs)($workerSite);
        $queue = new SyncQueue();
        $queue->setContainer($this->container);
        $queue->setConnectionName('sync');
        $bus = new Bus($this->container, static fn () => $queue);
        $this->container->instance(BusContract::class, $bus);

        return $bus;
    };
});

afterEach(function (): void {
    Queue::createPayloadUsing(null);
    Container::setInstance(null);
});

it("routes a site-bound job to the dispatching site's queue", function (): void {
    $queue = new RecordingQueue();
    $bus = new Bus($this->container, static fn () => $queue);

    switch_to_blog(2);
    $bus->dispatch(new RebuildSiteIndex());
    $bus->dispatch((new RebuildSiteIndex())->onQueue('default')->delay(30));
    restore_current_blog();

    expect($queue->pushed[0])->toMatchArray(['queue' => 'site-news', 'delay' => null])
        ->and($queue->pushed[1])->toMatchArray(['queue' => 'site-news', 'delay' => 30])
    ;
});

it('refuses to dispatch a site-bound job from a blog without a key', function (): void {
    $bus = new Bus($this->container, static fn () => new RecordingQueue());

    switch_to_blog(3);
    try {
        expect(fn () => $bus->dispatch(new RebuildSiteIndex()))->toThrow(InvalidSiteKey::class);
    } finally {
        restore_current_blog();
    }
});

it("runs on its own site's worker", function (): void {
    $bus = ($this->syncBus)(2);

    switch_to_blog(2);
    $bus->dispatch(new RebuildSiteIndex());
    restore_current_blog();

    expect(RebuildSiteIndex::$ranOn)->toBe([2]);
});

it("fails on another site's worker without running", function (): void {
    $bus = ($this->syncBus)(1);

    switch_to_blog(2);
    try {
        expect(fn () => $bus->dispatch(new RebuildSiteIndex()))
            ->toThrow(
                WrongSiteWorker::class,
                'needs the bindings of site "news" but ran on a worker for site "main"',
            )
        ;
    } finally {
        restore_current_blog();
    }

    expect(RebuildSiteIndex::$ranOn)->toBe([]);
});

it("fails the job on another site's queue worker without throwing", function (): void {
    $jobs = ($this->siteJobs)(1);
    $job = queuedJob('news', RebuildSiteIndex::class);

    $jobs->processing(new JobProcessing('redis', $job));

    expect($job->hasFailed())->toBeTrue()
        ->and($job->failedWith)->toBeInstanceOf(WrongSiteWorker::class)
    ;
});

it('leaves other jobs alone', function (): void {
    $jobs = ($this->siteJobs)(1);
    $network = queuedJob('news', LeavesSwitched::class);
    $own = queuedJob('main', RebuildSiteIndex::class);

    $jobs->processing(new JobProcessing('redis', $network));
    $jobs->processing(new JobProcessing('redis', $own));

    expect($network->hasFailed())->toBeFalse()
        ->and($own->hasFailed())->toBeFalse()
    ;
});

it('unwinds switches a job leaves', function (): void {
    ($this->syncBus)(1)->dispatch(new LeavesSwitched());

    expect(get_current_blog_id())->toBe(1)
        ->and(Site::switchDepth())->toBe(0)
    ;
});

it('unwinds only to where the job started', function (): void {
    $bus = ($this->syncBus)(1);

    switch_to_blog(2);
    $bus->dispatch(new LeavesSwitched());

    expect(get_current_blog_id())->toBe(2)
        ->and(Site::switchDepth())->toBe(1)
    ;
    restore_current_blog();
});
