<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Kleinweb\Lib\Database\Batch\MemoryCursor;
use Kleinweb\Lib\Database\Batch\OptionCursor;
use Kleinweb\Lib\Database\Batch\PostBatches;
use Kleinweb\Lib\Tests\Support\WordPress;

/**
 * Posts on the current blog, as IDs => type.
 *
 * @param array<int, string> $posts
 */
function seedPosts(array $posts, string $status = 'publish'): void
{
    foreach ($posts as $id => $type) {
        WordPress::$posts[WordPress::$blogId][$id] = [
            'post_type' => $type,
            'post_status' => $status,
        ];
    }
}

/**
 * @return list<int>
 */
function postIds(PostBatches $batches): array
{
    return $batches->lazy()->map(static fn (WP_Post $post): int => $post->ID)->values()->all();
}

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    seedPosts([
        3 => 'attachment',
        7 => 'post',
        8 => 'attachment',
        12 => 'attachment',
        40 => 'attachment',
    ]);
});

it('walks every matching post in ID order, a batch at a time', function (): void {
    $ids = postIds(PostBatches::query(['post_type' => 'attachment'])->size(2));

    expect($ids)->toBe([3, 8, 12, 40])
        // Keyset pages: each starts after the last ID of the one before.
        ->and(array_column(WordPress::$queries, 'after'))->toBe([0, 8, 40])
        ->and(array_column(WordPress::$queries, 'limit'))->toBe([2, 2, 2])
    ;
});

it('records progress after each batch and resumes from it', function (): void {
    $cursor = new MemoryCursor();

    // Stop part-way through the second batch.
    $seen = PostBatches::query(['post_type' => 'attachment'])->size(2)->resumeFrom($cursor)->lazy()
        ->takeWhile(static fn (WP_Post $post): bool => $post->ID < 40)
        ->map(static fn (WP_Post $post): int => $post->ID)
        ->values()
        ->all()
    ;

    expect($seen)->toBe([3, 8, 12])
        ->and($cursor->get())->toBe(8, 'the unfinished batch is not recorded')
    ;

    expect(postIds(PostBatches::query(['post_type' => 'attachment'])->size(2)->resumeFrom($cursor)))
        ->toBe([12, 40], 'it is done again on the next run')
        ->and($cursor->get())->toBe(40)
    ;
});

it('keeps an option cursor per blog', function (): void {
    WordPress::addSite(2, 'news');
    switch_to_blog(2);
    seedPosts([5 => 'attachment']);
    restore_current_blog();

    $cursor = new OptionCursor('kleinweb_test_cursor');
    postIds(PostBatches::query(['post_type' => 'attachment'])->resumeFrom($cursor));

    switch_to_blog(2);
    expect($cursor->get())->toBe(0);
    postIds(PostBatches::query(['post_type' => 'attachment'])->resumeFrom($cursor));
    expect($cursor->get())->toBe(5);
    restore_current_blog();

    expect($cursor->get())->toBe(40);
    $cursor->reset();
    expect($cursor->get())->toBe(0);
});

it('releases memory after each batch and restores what it changed', function (): void {
    $GLOBALS['wpdb']->queries = ['SELECT 1'];
    add_filter('ep_skip_query_integration', static fn () => false, 100);

    $deferred = PostBatches::query(['post_type' => 'attachment'])->size(2)->lazy()
        ->map(static fn (): bool => WordPress::$termCountingDeferred)
        ->unique()
        ->values()
        ->all()
    ;

    expect($deferred)->toBe([true], 'term counting deferred while walking')
        ->and(WordPress::$termCountingDeferred)->toBeFalse()
        ->and(WordPress::$cacheFlushes)->toBe(2)
        ->and($GLOBALS['wpdb']->queries)->toBe([])
        ->and(WordPress::$hooks['posts_where'][9999] ?? [])->toBe([])
        ->and(WordPress::$hooks['ep_skip_query_integration'][100] ?? [])->toHaveCount(1)
    ;
});

it('filters only its own query', function (): void {
    postIds(PostBatches::query(['post_type' => 'attachment'])->size(2));

    // Another query, run while the batcher's filter was not there.
    $args = ['post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 10];
    expect((new WP_Query())->query($args))->toHaveCount(5);
});
