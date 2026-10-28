<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy;

use DomainException;
use Illuminate\Config\Repository as Config;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Bootstrappers\ConfigBootstrapper;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\Events\SiteSwitched;
use Kleinweb\Lib\Tenancy\SiteContext;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\FailingOnBlog;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use LogicException;
use RuntimeException;
use Throwable;

use function add_action;
use function array_key_exists;
use function restore_current_blog;
use function switch_to_blog;
use function sys_get_temp_dir;

/**
 * Following `switch_to_blog()`, against the example project's site plugins:
 * blog 1 `main` (no site plugin), 2 `news`, 3 no key, 4 a blog whose
 * bootstrapper fails, 5 `events`.
 */
final class SiteContextTest extends TestCase
{
    private const string TICKETING = 'https://tickets.example.test/api';

    private Config $config;

    private SiteContext $context;

    /** @var list<class-string> */
    private array $reported = [];

    private int $switches = 0;

    private bool $failListener = false;

    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main');
        WordPress::addSite(2, 'news');
        WordPress::addSite(3);
        WordPress::addSite(4, 'broken');
        WordPress::addSite(5, 'events');

        $this->config = new Config([
            'mail' => ['default' => 'log', 'from' => ['address' => 'shared@x', 'name' => 'Shared']],
            'post-types' => self::exampleConfig('post-types'),
            'tenancy' => ['meta_config' => ['contact_email' => 'mail.from.address']],
        ]);

        $events = new Dispatcher();
        $events->listen(SiteSwitched::class, function (SiteSwitched $event): void {
            $this->switches++;
            if ($this->failListener && $event->site?->key()?->value === 'news') {
                throw new LogicException('listener');
            }
        });

        $this->context = new SiteContext(
            new SiteRepository(),
            $events,
            [new FailingOnBlog(4)],
            [new ConfigBootstrapper($this->config, new SiteManifest(
                new Application(sys_get_temp_dir()),
                new Filesystem(),
            ))],
            function (Throwable $e): void {
                $this->reported[] = $e::class;
            },
        );

        add_action('switch_blog', fn (int $id) => $this->context->sync($id), 20);
        $this->context->initialize(Site::find(1), 1);
    }

    public function testASiteWithoutASitePluginHasTheSharedConfig(): void
    {
        self::assertSame('shared@x', $this->config->get('mail.from.address'));
    }

    public function testASitePluginsConfigFollowsTheSwitch(): void
    {
        switch_to_blog(2);
        self::assertSame('news@klein.temple.edu', $this->config->get('mail.from.address'));
        self::assertSame('Klein News', $this->config->get('mail.from.name'));
        self::assertSame('log', $this->config->get('mail.default'));

        restore_current_blog();
        self::assertSame('shared@x', $this->config->get('mail.from.address'));
        self::assertSame(self::exampleConfig('post-types'), $this->config->get('post-types'));
    }

    public function testPostTypeOverlaysAddChangeAndReplace(): void
    {
        switch_to_blog(2);

        self::assertSame('Stories', $this->config->get('post-types.post_types.story.names.plural'));
        $postTypes = $this->config->get('post-types.post_types');
        self::assertSame('dashicons-businessperson', $postTypes['profile']['menu_icon']);
        self::assertSame('people', $this->config->get('post-types.post_types.profile.names.slug'));
        $topic = $this->config->get('post-types.taxonomies.topic');
        self::assertSame(['post', 'profile', 'story'], $topic['post_types']);
    }

    public function testNullRemovesASharedTypeAndSiteOnlyFilesApplyOnlyThere(): void
    {
        switch_to_blog(5);
        self::assertFalse(array_key_exists('profile', $this->config->get('post-types.post_types')));
        self::assertSame('events', $this->config->get('post-types.post_types.event.names.slug'));
        self::assertSame(self::TICKETING, $this->config->get('services.ticketing.endpoint'));

        restore_current_blog();
        self::assertNull($this->config->get('services.ticketing'));
    }

    public function testASitePluginAppliesWithoutActivation(): void
    {
        // The example's plugin lists are empty: site plugins go by key.
        switch_to_blog(5);
        self::assertSame(self::TICKETING, $this->config->get('services.ticketing.endpoint'));
        restore_current_blog();
    }

    public function testBlogmetaValuesMappedInTenancyConfigApplyOnTop(): void
    {
        WordPress::$meta[5]['contact_email'] = 'tickets@klein.temple.edu';
        WordPress::$meta[2]['contact_email'] = '';

        switch_to_blog(5);
        self::assertSame('tickets@klein.temple.edu', $this->config->get('mail.from.address'));
        restore_current_blog();

        switch_to_blog(2);
        // Empty meta is ignored.
        self::assertSame('news@klein.temple.edu', $this->config->get('mail.from.address'));
        restore_current_blog();

        self::assertSame('shared@x', $this->config->get('mail.from.address'));
    }

    public function testNestedSwitchesUnwindInOrder(): void
    {
        switch_to_blog(2);
        switch_to_blog(3);
        self::assertSame('shared@x', $this->config->get('mail.from.address'));
        restore_current_blog();
        self::assertSame('news@klein.temple.edu', $this->config->get('mail.from.address'));
        restore_current_blog();
        self::assertSame('shared@x', $this->config->get('mail.from.address'));
    }

    public function testSwitchingToTheCurrentBlogAnnouncesNothing(): void
    {
        $before = $this->switches;
        switch_to_blog(1);
        restore_current_blog();

        self::assertSame($before, $this->switches);
    }

    public function testFailuresAreReportedAndNeverEscapeTheSwitch(): void
    {
        $this->failListener = true;

        switch_to_blog(4);
        self::assertSame(4, \get_current_blog_id());
        restore_current_blog();

        switch_to_blog(2);
        self::assertSame('news@klein.temple.edu', $this->config->get('mail.from.address'));
        restore_current_blog();

        self::assertContains(RuntimeException::class, $this->reported);
        self::assertContains(LogicException::class, $this->reported);
        self::assertSame(1, \get_current_blog_id());
    }

    public function testSiteRunUnwindsUnbalancedSwitchesAndExceptions(): void
    {
        $news = Site::find(2);
        self::assertNotNull($news);

        $news->run(static function (): void {
            switch_to_blog(3);
            switch_to_blog(2);
        });
        self::assertSame(1, \get_current_blog_id());
        self::assertSame(0, Site::switchDepth());

        try {
            $news->run(static function (): never {
                switch_to_blog(3);

                throw new DomainException('x');
            });
        } catch (DomainException) {
            // Expected.
        }

        self::assertSame(1, \get_current_blog_id());
        self::assertSame(0, Site::switchDepth());
        self::assertSame('shared@x', $this->config->get('mail.from.address'));
    }

    public function testSiteRunInsideAnOuterSwitchReturnsThere(): void
    {
        switch_to_blog(3);
        Site::find(2)?->run(static fn () => null);

        self::assertSame(3, \get_current_blog_id());
        self::assertSame(1, Site::switchDepth());
        restore_current_blog();
    }
}
