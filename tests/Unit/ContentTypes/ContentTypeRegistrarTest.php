<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\ContentTypes;

use Illuminate\Config\Repository;
use Kleinweb\Lib\ContentTypes\ContentTypeRegistrar;
use Kleinweb\Lib\Tenancy\Config\ConfigOverlay;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Closure;

use function post_type_exists;
use function register_taxonomy;
use function register_taxonomy_for_object_type;

/**
 * Switching registrations between the example's sites: main (the shared
 * config), news and events (their site plugins' overlays).  The fake
 * WordPress follows Core: unregistering a post type unlinks its taxonomies.
 */
final class ContentTypeRegistrarTest extends TestCase
{
    private ConfigOverlay $overlay;

    /** @var array<string, mixed> the applied site's leaves */
    private array $current = [];

    private ContentTypeRegistrar $registrar;

    /** @var array{0: array<string, mixed>, 1: array<string, mixed>} */
    private array $main;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new Repository(['post-types' => self::exampleConfig('post-types')]);
        $this->overlay = new ConfigOverlay($config);
        $this->overlay->apply([]);
        $this->registrar = new ContentTypeRegistrar($config);
        $this->registrar->registerAll();

        // Another plugin links its own taxonomy to a type from config.
        register_taxonomy('audience', ['post']);
        register_taxonomy_for_object_type('audience', 'profile');

        $this->main = WordPress::contentTypes();
    }

    public function testRegistersTheSharedTypesWithExtendedCptsArguments(): void
    {
        self::assertTrue(post_type_exists('profile'));
        self::assertSame(['post', 'profile'], WordPress::linkedTypes('topic'));
        $args = WordPress::$postTypes['profile']['args'];
        self::assertSame('Profiles', $args['labels']['name'] ?? null);
        self::assertSame('people', $args['rewrite']['slug'] ?? null);
    }

    public function testEnteringASiteAddsAndReplacesTypes(): void
    {
        $this->visit('news', function (): void {
            self::assertTrue(post_type_exists('story'));
            self::assertSame(['story'], WordPress::linkedTypes('desk'));
            self::assertSame('dashicons-businessperson', self::profileIcon());
            self::assertSame(['post', 'profile', 'story'], WordPress::linkedTypes('topic'));
            // Replacing profile kept the other plugin's link to it.
            self::assertSame(['post', 'profile'], WordPress::linkedTypes('audience'));
        });

        self::assertSame($this->main, WordPress::contentTypes());
    }

    public function testEnteringASiteRemovesTypesItDoesNotHave(): void
    {
        $this->visit('events', function (): void {
            self::assertFalse(post_type_exists('profile'));
            self::assertTrue(post_type_exists('event'));
            self::assertSame(['event', 'post'], WordPress::linkedTypes('topic'));
            self::assertSame(['post'], WordPress::linkedTypes('audience'));
        });

        self::assertSame($this->main, WordPress::contentTypes(), 'links restored with the type');
    }

    public function testNestedVisitsUnwind(): void
    {
        $this->visit('news', function (): void {
            $this->visit('events', static function (): void {
                self::assertFalse(post_type_exists('story'));
                self::assertTrue(post_type_exists('event'));
            });

            self::assertTrue(post_type_exists('story'));
            self::assertSame(['story'], WordPress::linkedTypes('desk'));
            self::assertSame('dashicons-businessperson', self::profileIcon());
        });

        self::assertSame($this->main, WordPress::contentTypes());
    }

    public function testEnteringTheSameDefinitionsChangesNothing(): void
    {
        $changes = $this->registrar->enter();

        self::assertSame([], $changes->addedPostTypes);
        self::assertSame([], $changes->replacedPostTypes);
        self::assertSame([], $changes->removedPostTypes);
        self::assertSame([], $changes->removedTaxonomies);

        $this->registrar->leave($changes);
        self::assertSame($this->main, WordPress::contentTypes());
    }

    /**
     * As `runFor()` does after `switch_to_blog()`, with the overlays swapped
     * the way ConfigBootstrapper swaps them: the outer site's reverted, the
     * visited site's applied, and back again afterwards.
     */
    private function visit(string $key, Closure $callback): void
    {
        $overlay = require self::example(
            "wp-content/plugins/kleinweb-site-{$key}/config/post-types.php",
        );
        self::assertIsArray($overlay);

        $outer = $this->current;
        $this->overlay->revert();
        $this->current = ConfigOverlay::leaves(['post-types' => $overlay]);
        $this->overlay->apply($this->current);
        $changes = $this->registrar->enter();

        try {
            $callback();
        } finally {
            $this->registrar->leave($changes);
            $this->overlay->revert();
            $this->current = $outer;
            $this->overlay->apply($outer);
        }
    }

    private static function profileIcon(): mixed
    {
        return WordPress::$postTypes['profile']['args']['menu_icon'] ?? null;
    }
}
