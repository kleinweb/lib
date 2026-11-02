<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * `forEachSite()` in a process booted for the main site, as `wp acorn …`.
 * Blogs: 1 `main`, 2 `news`, 3 no key, 5 `events`.
 */

declare(strict_types=1);

use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Kleinweb\Lib\ContentTypes\ContentTypesServiceProvider;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\TenancyServiceProvider;
use Kleinweb\Lib\Tests\Support\Commands\VisitSites;
use Kleinweb\Lib\Tests\Support\RecordingExceptionHandler;
use Kleinweb\Lib\Tests\Support\WordPress;

use function Kleinweb\Lib\Tests\exampleConfig;
use function Kleinweb\Lib\Tests\runCommand;
use function Kleinweb\Lib\Tests\tempPath;
use function Kleinweb\Lib\Tests\testApplication;

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(3);
    WordPress::addSite(5, 'events');

    $this->app = testApplication(tempPath('visit'));
    Container::setInstance($this->app);
    $this->app->instance('config', new Config([
        'mail' => ['from' => ['address' => 'shared@x']],
        'post-types' => exampleConfig('post-types'),
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
    ]));
    $this->handler = new RecordingExceptionHandler();
    $this->app->instance(ExceptionHandler::class, $this->handler);
    $this->app->register(TenancyServiceProvider::class);
    $this->app->register(ContentTypesServiceProvider::class);
    $this->app->boot();
    do_action('init');

    /* @var array<int, array<string, mixed>> $seen blog ID => what the callback saw */
    $this->seen = [];
    $this->record = function (Site $site): void {
        expect(get_current_blog_id())->toBe($site->id());
        $this->seen[$site->id()] = [
            'mail' => config('mail.from.address'),
            'story' => post_type_exists('story'),
            'event' => post_type_exists('event'),
            'profile' => post_type_exists('profile'),
        ];
    };

    $this->visit = fn (Closure $callback, array $input = []): array => runCommand(
        new VisitSites($callback),
        $this->app,
        $input,
    );
});

afterEach(function (): void {
    Container::setInstance(null);
});

it('gives each site its own data, config and post types, then restores', function (): void {
    [$code] = ($this->visit)($this->record);

    expect($code)->toBe(Command::SUCCESS)
        ->and(array_keys($this->seen))->toBe([1, 2, 3, 5])
        ->and($this->seen[2])->toMatchArray(['mail' => 'news@klein.temple.edu', 'story' => true])
        ->and($this->seen[5])->toMatchArray(['story' => false, 'event' => true, 'profile' => false])
        ->and($this->seen[3]['mail'])->toBe('shared@x')
        ->and(get_current_blog_id())->toBe(1)
        ->and(Site::switchDepth())->toBe(0)
        ->and(post_type_exists('story'))->toBeFalse()
        ->and(post_type_exists('profile'))->toBeTrue()
        ->and(config('mail.from.address'))->toBe('shared@x')
    ;
});

it('can leave post types alone', function (): void {
    ($this->visit)($this->record, ['--no-content-types' => true]);

    expect($this->seen[2]['story'])->toBeFalse()
        ->and($this->seen[2]['mail'])->toBe('news@klein.temple.edu', 'config still follows')
    ;
});

it('selects sites by key', function (): void {
    ($this->visit)($this->record, ['--sites' => ['news', 'events'], '--skip-sites' => ['events']]);

    expect(array_keys($this->seen))->toBe([2]);
});

it('reports a failing site and carries on', function (): void {
    [$code, $output] = ($this->visit)(function (Site $site): mixed {
        ($this->record)($site);

        return match ($site->id()) {
            2 => throw new RuntimeException('news broke'),
            3 => false,
            5 => 7,
            default => null,
        };
    });

    expect($code)->toBe(Command::FAILURE)
        ->and(array_keys($this->seen))->toBe([1, 2, 3, 5])
        ->and($output)->toContain('news: news broke', 'Failed for 3 site(s): news, blog 3, events')
        ->and($this->handler->reported)->toHaveCount(1)
        ->and(get_current_blog_id())->toBe(1)
        ->and(post_type_exists('story'))->toBeFalse()
    ;
});
