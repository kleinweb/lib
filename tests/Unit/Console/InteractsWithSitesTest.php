<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Console;

use Closure;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Kleinweb\Lib\ContentTypes\ContentTypesServiceProvider;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\TenancyServiceProvider;
use Kleinweb\Lib\Tests\Support\Commands\VisitSites;
use Kleinweb\Lib\Tests\Support\RecordingExceptionHandler;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function config;
use function do_action;
use function get_current_blog_id;
use function post_type_exists;
use function sys_get_temp_dir;
use function uniqid;

/**
 * `forEachSite()` in a process booted for the main site, as `wp acorn …`.
 * Blogs: 1 `main`, 2 `news`, 3 no key, 5 `events`.
 */
final class InteractsWithSitesTest extends TestCase
{
    private Application $app;

    private RecordingExceptionHandler $handler;

    /** @var array<int, array<string, mixed>> blog ID => what the callback saw */
    private array $seen = [];

    protected function setUp(): void
    {
        parent::setUp();

        WordPress::addSite(1, 'main');
        WordPress::addSite(2, 'news');
        WordPress::addSite(3);
        WordPress::addSite(5, 'events');

        $this->app = new Application(sys_get_temp_dir() . '/' . uniqid('kleinweb-visit-', true));
        \Illuminate\Container\Container::setInstance($this->app);
        $this->app->instance('config', new Config([
            'mail' => ['from' => ['address' => 'shared@x']],
            'post-types' => self::exampleConfig('post-types'),
            'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
        ]));
        $this->app->instance('files', new Filesystem());
        $this->handler = new RecordingExceptionHandler();
        $this->app->instance(ExceptionHandler::class, $this->handler);
        $this->app->register(TenancyServiceProvider::class);
        $this->app->register(ContentTypesServiceProvider::class);
        $this->app->boot();
        do_action('init');
    }

    protected function tearDown(): void
    {
        \Illuminate\Container\Container::setInstance(null);
    }

    public function testEachSiteSeesItsOwnDataConfigAndPostTypes(): void
    {
        [$code] = $this->visit($this->record(...));

        self::assertSame(Command::SUCCESS, $code);
        self::assertSame([1, 2, 3, 5], array_keys($this->seen));
        self::assertSame('news@klein.temple.edu', $this->seen[2]['mail']);
        self::assertTrue($this->seen[2]['story']);
        self::assertFalse($this->seen[5]['story']);
        self::assertTrue($this->seen[5]['event']);
        self::assertFalse($this->seen[5]['profile']);
        self::assertSame('shared@x', $this->seen[3]['mail']);

        self::assertSame(1, get_current_blog_id());
        self::assertSame(0, Site::switchDepth());
        self::assertFalse(post_type_exists('story'));
        self::assertTrue(post_type_exists('profile'));
        self::assertSame('shared@x', config('mail.from.address'));
    }

    public function testPostTypesCanBeLeftAlone(): void
    {
        $this->visit($this->record(...), ['--no-content-types' => true]);

        self::assertFalse($this->seen[2]['story']);
        self::assertSame('news@klein.temple.edu', $this->seen[2]['mail'], 'config still follows');
    }

    public function testSelectsSitesByKey(): void
    {
        $this->visit($this->record(...), [
            '--sites' => ['news', 'events'],
            '--skip-sites' => ['events'],
        ]);

        self::assertSame([2], array_keys($this->seen));
    }

    public function testAFailingSiteIsReportedAndTheLoopCarriesOn(): void
    {
        [$code, $output] = $this->visit(function (Site $site): mixed {
            $this->record($site);

            return match ($site->id()) {
                2 => throw new RuntimeException('news broke'),
                3 => false,
                5 => 7,
                default => null,
            };
        });

        self::assertSame(Command::FAILURE, $code);
        self::assertSame([1, 2, 3, 5], array_keys($this->seen));
        self::assertStringContainsString('news: news broke', $output);
        self::assertStringContainsString('Failed for 3 site(s): news, blog 3, events', $output);
        self::assertCount(1, $this->handler->reported);
        self::assertSame(1, get_current_blog_id());
        self::assertFalse(post_type_exists('story'));
    }

    private function record(Site $site): void
    {
        $this->seen[$site->id()] = [
            'mail' => config('mail.from.address'),
            'story' => post_type_exists('story'),
            'event' => post_type_exists('event'),
            'profile' => post_type_exists('profile'),
        ];
        self::assertSame($site->id(), get_current_blog_id());
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{int, string}
     */
    private function visit(Closure $callback, array $input = []): array
    {
        $command = new VisitSites($callback);
        $command->setLaravel($this->app);
        $output = new BufferedOutput();
        $code = $command->run(new ArrayInput($input), $output);

        return [$code, $output->fetch()];
    }
}
