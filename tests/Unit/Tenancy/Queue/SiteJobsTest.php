<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy\Queue;

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
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;

use function restore_current_blog;
use function switch_to_blog;

/**
 * Blogs: 1 `main`, 2 `news`, 3 no key, 5 `events`.
 */
final class SiteJobsTest extends TestCase
{
    private Container $container;

    private Dispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    protected function tearDown(): void
    {
        Queue::createPayloadUsing(null);
        Container::setInstance(null);
    }

    public function testRoutesToTheDispatchingSitesQueue(): void
    {
        $queue = new RecordingQueue();
        $bus = new Bus($this->container, static fn () => $queue);

        switch_to_blog(2);
        $bus->dispatch(new RebuildSiteIndex());
        $bus->dispatch((new RebuildSiteIndex())->onQueue('default')->delay(30));
        restore_current_blog();

        self::assertSame('site-news', $queue->pushed[0]['queue']);
        self::assertNull($queue->pushed[0]['delay']);
        self::assertSame('site-news', $queue->pushed[1]['queue'], 'the site queue wins');
        self::assertSame(30, $queue->pushed[1]['delay']);
    }

    public function testDispatchingFromABlogWithoutAKeyThrows(): void
    {
        $bus = new Bus($this->container, static fn () => new RecordingQueue());

        switch_to_blog(3);
        try {
            $this->expectException(InvalidSiteKey::class);
            $bus->dispatch(new RebuildSiteIndex());
        } finally {
            restore_current_blog();
        }
    }

    public function testRunsOnItsOwnSitesWorker(): void
    {
        $bus = $this->syncBus(workerSite: 2);

        switch_to_blog(2);
        $bus->dispatch(new RebuildSiteIndex());
        restore_current_blog();

        self::assertSame([2], RebuildSiteIndex::$ranOn);
    }

    public function testFailsOnAnotherSitesWorkerWithoutRunning(): void
    {
        $bus = $this->syncBus(workerSite: 1);

        switch_to_blog(2);
        try {
            $bus->dispatch(new RebuildSiteIndex());
            self::fail('Expected WrongSiteWorker.');
        } catch (WrongSiteWorker $e) {
            self::assertStringContainsString('site "news"', $e->getMessage());
            self::assertStringContainsString('worker for site "main"', $e->getMessage());
        } finally {
            restore_current_blog();
        }

        self::assertSame([], RebuildSiteIndex::$ranOn);
    }

    public function testAWorkerForAnotherSiteFailsTheJobWithoutThrowing(): void
    {
        $jobs = $this->siteJobs(workerSite: 1);
        $job = self::job('news', RebuildSiteIndex::class);

        $jobs->processing(new JobProcessing('redis', $job));

        self::assertTrue($job->hasFailed());
        self::assertInstanceOf(WrongSiteWorker::class, $job->failedWith);
    }

    public function testAWorkerLeavesOtherJobsAlone(): void
    {
        $jobs = $this->siteJobs(workerSite: 1);
        $network = self::job('news', LeavesSwitched::class);
        $own = self::job('main', RebuildSiteIndex::class);

        $jobs->processing(new JobProcessing('redis', $network));
        $jobs->processing(new JobProcessing('redis', $own));

        self::assertFalse($network->hasFailed());
        self::assertFalse($own->hasFailed());
    }

    public function testUnwindsSwitchesAJobLeaves(): void
    {
        $bus = $this->syncBus(workerSite: 1);

        $bus->dispatch(new LeavesSwitched());

        self::assertSame(1, \get_current_blog_id());
        self::assertSame(0, Site::switchDepth());
    }

    public function testUnwindsOnlyToWhereTheJobStarted(): void
    {
        $bus = $this->syncBus(workerSite: 1);

        switch_to_blog(2);
        $bus->dispatch(new LeavesSwitched());

        self::assertSame(2, \get_current_blog_id());
        self::assertSame(1, Site::switchDepth());
        restore_current_blog();
    }

    /**
     * @param class-string $class
     */
    private static function job(string $siteKey, string $class): PayloadJob
    {
        return new PayloadJob(['siteKey' => $siteKey, 'data' => ['commandName' => $class]]);
    }

    /**
     * The listeners, in a process initialized for a blog.
     */
    private function siteJobs(int $workerSite): SiteJobs
    {
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
    }

    /**
     * A bus on the sync driver, in a process initialized for a blog.
     */
    private function syncBus(int $workerSite): Bus
    {
        $this->siteJobs($workerSite);

        $queue = new SyncQueue();
        $queue->setContainer($this->container);
        $queue->setConnectionName('sync');

        $bus = new Bus($this->container, static fn () => $queue);
        $this->container->instance(BusContract::class, $bus);

        return $bus;
    }
}
