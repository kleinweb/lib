<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * Switching registrations between the example's sites: main (the shared
 * config), news and events (their site plugins' overlays).  The fake
 * WordPress follows Core: unregistering a post type unlinks its taxonomies.
 */

declare(strict_types=1);

use Illuminate\Config\Repository;
use Kleinweb\Lib\ContentTypes\ContentTypeRegistrar;
use Kleinweb\Lib\Tenancy\Config\ConfigOverlay;
use Kleinweb\Lib\Tests\Support\WordPress;

use function Kleinweb\Lib\Tests\exampleConfig;
use function Kleinweb\Lib\Tests\sitePluginConfig;

function profileArgs(): mixed
{
    return WordPress::$postTypes['profile']['args'] ?? null;
}

beforeEach(function (): void {
    $config = new Repository(['post-types' => exampleConfig('post-types')]);
    $overlay = new ConfigOverlay($config);
    $this->registrar = new ContentTypeRegistrar($config);
    $this->registrar->registerAll();

    // Another plugin links its own taxonomy to a type from config.
    register_taxonomy('audience', ['post']);
    register_taxonomy_for_object_type('audience', 'profile');

    $this->main = WordPress::contentTypes();

    /*
     * As runFor() does after switch_to_blog(), with overlays swapped the
     * way ConfigBootstrapper swaps them: the outer site's reverted, the
     * visited site's applied, and back again afterwards.
     */
    $this->current = [];
    $this->visit = function (string $key, Closure $callback) use ($overlay): void {
        $outer = $this->current;
        $overlay->revert();
        $this->current = ConfigOverlay::leaves([
            'post-types' => sitePluginConfig($key, 'post-types'),
        ]);
        $overlay->apply($this->current);
        $changes = $this->registrar->enter();

        try {
            $callback();
        } finally {
            $this->registrar->leave($changes);
            $overlay->revert();
            $this->current = $outer;
            $overlay->apply($outer);
        }
    };
});

it('registers the shared types with Extended CPTs arguments', function (): void {
    expect(post_type_exists('profile'))->toBeTrue()
        ->and(WordPress::linkedTypes('topic'))->toBe(['post', 'profile'])
        ->and(profileArgs()['labels']['name'] ?? null)->toBe('Profiles')
        ->and(profileArgs()['rewrite']['slug'] ?? null)->toBe('people')
    ;
});

it('adds and replaces types for the site it enters, then restores them', function (): void {
    ($this->visit)('news', function (): void {
        expect(post_type_exists('story'))->toBeTrue()
            ->and(WordPress::linkedTypes('desk'))->toBe(['story'])
            ->and(profileArgs()['menu_icon'] ?? null)->toBe('dashicons-businessperson')
            ->and(WordPress::linkedTypes('topic'))->toBe(['post', 'profile', 'story'])
            // The other plugin's link survives.
            ->and(WordPress::linkedTypes('audience'))->toBe(['post', 'profile'])
        ;
    });

    expect(WordPress::contentTypes())->toBe($this->main);
});

it('removes types the site does not have, then restores them with their links', function (): void {
    ($this->visit)('events', function (): void {
        expect(post_type_exists('profile'))->toBeFalse()
            ->and(post_type_exists('event'))->toBeTrue()
            ->and(WordPress::linkedTypes('topic'))->toBe(['event', 'post'])
            ->and(WordPress::linkedTypes('audience'))->toBe(['post'])
        ;
    });

    expect(WordPress::contentTypes())->toBe($this->main);
});

it('unwinds nested visits', function (): void {
    ($this->visit)('news', function (): void {
        ($this->visit)('events', static function (): void {
            expect(post_type_exists('story'))->toBeFalse()
                ->and(post_type_exists('event'))->toBeTrue()
            ;
        });

        expect(post_type_exists('story'))->toBeTrue()
            ->and(WordPress::linkedTypes('desk'))->toBe(['story'])
            ->and(profileArgs()['menu_icon'] ?? null)->toBe('dashicons-businessperson')
        ;
    });

    expect(WordPress::contentTypes())->toBe($this->main);
});

it('changes nothing when the definitions are the same', function (): void {
    $changes = $this->registrar->enter();

    expect($changes->addedPostTypes)->toBe([])
        ->and($changes->replacedPostTypes)->toBe([])
        ->and($changes->removedPostTypes)->toBe([])
        ->and($changes->removedTaxonomies)->toBe([])
    ;

    $this->registrar->leave($changes);
    expect(WordPress::contentTypes())->toBe($this->main);
});
