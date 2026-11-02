<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * Following `switch_to_blog()`, against the example project's site plugins:
 * blog 1 `main` (no site plugin), 2 `news`, 3 no key, 4 a blog whose
 * bootstrapper fails, 5 `events`.
 */

declare(strict_types=1);

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
use Kleinweb\Lib\Tests\Support\WordPress;

use function Kleinweb\Lib\Tests\exampleConfig;

const TICKETING = 'https://tickets.example.test/api';

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(3);
    WordPress::addSite(4, 'broken');
    WordPress::addSite(5, 'events');

    $this->config = new Config([
        'mail' => ['default' => 'log', 'from' => ['address' => 'shared@x', 'name' => 'Shared']],
        'post-types' => exampleConfig('post-types'),
        'tenancy' => ['meta_config' => ['contact_email' => 'mail.from.address']],
    ]);
    $this->reported = [];
    $this->switches = 0;
    $this->failListener = false;

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

    $this->mailFrom = fn (): mixed => $this->config->get('mail.from.address');
});

it('gives a site without a site plugin the shared config', function (): void {
    expect(($this->mailFrom)())->toBe('shared@x');
});

it("applies a site plugin's config and reverts it, following the switch", function (): void {
    switch_to_blog(2);
    expect(($this->mailFrom)())->toBe('news@klein.temple.edu')
        ->and($this->config->get('mail.from.name'))->toBe('Klein News')
        ->and($this->config->get('mail.default'))->toBe('log')
    ;

    restore_current_blog();
    expect(($this->mailFrom)())->toBe('shared@x')
        ->and($this->config->get('post-types'))->toBe(exampleConfig('post-types'))
    ;
});

it('adds, changes and replaces post type definitions', function (): void {
    switch_to_blog(2);

    $postTypes = $this->config->get('post-types.post_types');
    expect($postTypes['story']['names']['plural'])->toBe('Stories')
        ->and($postTypes['profile']['menu_icon'])->toBe('dashicons-businessperson')
        ->and($postTypes['profile']['names']['slug'])->toBe('people')
        ->and($this->config->get('post-types.taxonomies.topic')['post_types'])
        ->toBe(['post', 'profile', 'story'])
    ;
});

it('removes a shared type with null; site-only files apply only there', function (): void {
    switch_to_blog(5);
    expect($this->config->get('post-types.post_types'))->not->toHaveKey('profile')
        ->and($this->config->get('post-types.post_types.event.names.slug'))->toBe('events')
        ->and($this->config->get('services.ticketing.endpoint'))->toBe(TICKETING)
    ;

    restore_current_blog();
    expect($this->config->get('services.ticketing'))->toBeNull();
});

it('applies a site plugin without activation', function (): void {
    // The example's plugin lists are empty: site plugins go by key.
    switch_to_blog(5);
    expect($this->config->get('services.ticketing.endpoint'))->toBe(TICKETING);
    restore_current_blog();
});

it('applies blogmeta values mapped in tenancy config on top', function (): void {
    WordPress::$meta[5]['contact_email'] = 'tickets@klein.temple.edu';
    WordPress::$meta[2]['contact_email'] = '';

    switch_to_blog(5);
    expect(($this->mailFrom)())->toBe('tickets@klein.temple.edu');
    restore_current_blog();

    switch_to_blog(2);
    expect(($this->mailFrom)())->toBe('news@klein.temple.edu', 'empty meta is ignored');
    restore_current_blog();

    expect(($this->mailFrom)())->toBe('shared@x');
});

it('unwinds nested switches in order', function (): void {
    switch_to_blog(2);
    switch_to_blog(3);
    expect(($this->mailFrom)())->toBe('shared@x');
    restore_current_blog();
    expect(($this->mailFrom)())->toBe('news@klein.temple.edu');
    restore_current_blog();
    expect(($this->mailFrom)())->toBe('shared@x');
});

it('announces nothing for a switch to the current blog', function (): void {
    $before = $this->switches;
    switch_to_blog(1);
    restore_current_blog();

    expect($this->switches)->toBe($before);
});

it('reports failures and never lets them escape a switch', function (): void {
    $this->failListener = true;

    switch_to_blog(4);
    expect(get_current_blog_id())->toBe(4);
    restore_current_blog();

    switch_to_blog(2);
    expect(($this->mailFrom)())->toBe('news@klein.temple.edu');
    restore_current_blog();

    expect($this->reported)->toContain(RuntimeException::class, LogicException::class)
        ->and(get_current_blog_id())->toBe(1)
    ;
});

it('unwinds unbalanced switches and exceptions in Site::run()', function (): void {
    $news = Site::find(2);

    $news?->run(static function (): void {
        switch_to_blog(3);
        switch_to_blog(2);
    });
    expect(get_current_blog_id())->toBe(1)
        ->and(Site::switchDepth())->toBe(0)
    ;

    expect(fn () => $news?->run(static function (): never {
        switch_to_blog(3);

        throw new DomainException('x');
    }))->toThrow(DomainException::class);

    expect(get_current_blog_id())->toBe(1)
        ->and(Site::switchDepth())->toBe(0)
        ->and(($this->mailFrom)())->toBe('shared@x')
    ;
});

it('returns to an outer switch after Site::run()', function (): void {
    switch_to_blog(3);
    Site::find(2)?->run(static fn () => null);

    expect(get_current_blog_id())->toBe(3)
        ->and(Site::switchDepth())->toBe(1);
    restore_current_blog();
});
