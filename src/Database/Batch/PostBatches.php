<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Database\Batch;

use Illuminate\Support\LazyCollection;
use WP_Post;
use WP_Query;

use function add_filter;
use function end;
use function function_exists;
use function is_object;
use function property_exists;
use function remove_filter;
use function sprintf;
use function wp_cache_flush;
use function wp_cache_flush_runtime;
use function wp_cache_supports;
use function wp_defer_term_counting;
use function wp_using_ext_object_cache;

/**
 * Every post matching a query, in ID order, a batch at a time.
 *
 * ```php
 * PostBatches::query(['post_type' => 'attachment'])
 *     ->resumeFrom(new OptionCursor('kleinweb_cursor_delete_dead'))
 *     ->lazy()
 *     ->each(fn (WP_Post $post) => ...);
 * ```
 *
 * Each batch is a `WP_Query` for posts with an ID above the last one seen
 * (keyset pagination), so the cost of a batch does not grow with how far
 * the walk has got, and posts added or deleted meanwhile do not shift the
 * pages.  Query arguments other than ordering and paging are passed through.
 *
 * Between batches the cursor records the last ID finished, so a run that is
 * stopped resumes after it; a batch interrupted part-way is done again, so
 * the work per post should be safe to repeat.  The query log and the
 * in-memory object cache are emptied too, so memory does not grow with the
 * number of posts.  While walking, term counting is deferred and
 * ElasticPress is bypassed.
 */
final class PostBatches
{
    private Cursor $cursor;

    /**
     * @param array<string, mixed> $args WP_Query arguments
     */
    private function __construct(
        private readonly array $args,
        private int $size = 100,
    ) {
        $this->cursor = new MemoryCursor();
    }

    /**
     * @param array<string, mixed> $args WP_Query arguments
     */
    public static function query(array $args): self
    {
        return new self($args);
    }

    public function resumeFrom(Cursor $cursor): self
    {
        $this->cursor = $cursor;

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = max(1, $size);

        return $this;
    }

    /**
     * @return LazyCollection<int, WP_Post>
     */
    public function lazy(): LazyCollection
    {
        return LazyCollection::make(function () {
            self::deferTermCounting(true);
            add_filter('ep_skip_query_integration', '__return_true', 100);

            try {
                $after = $this->cursor->get();

                while (($posts = $this->batch($after)) !== []) {
                    yield from $posts;

                    $last = end($posts);
                    $after = $last->ID;
                    $this->cursor->set($after);
                    self::releaseMemory();
                }
            } finally {
                remove_filter('ep_skip_query_integration', '__return_true', 100);
                self::deferTermCounting(false);
            }
        });
    }

    /**
     * Posts with an ID above `$after`, lowest first.
     *
     * @return list<WP_Post>
     */
    private function batch(int $after): array
    {
        $query = new WP_Query();

        // Only this query, not others that hooks run while it runs.
        $where = static fn (string $where, mixed $target): string => ($target === $query)
            ? sprintf(' AND %s.ID > %d %s', $GLOBALS['wpdb']->posts, $after, $where)
            : $where;

        add_filter('posts_where', $where, 9999, 2);

        try {
            $posts = $query->query([
                'post_type' => 'any',
                'post_status' => 'any',
                ...$this->args,
                'posts_per_page' => $this->size,
                'paged' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
                'suppress_filters' => false,
                'fields' => 'all',
            ]);
        } finally {
            remove_filter('posts_where', $where, 9999);
        }

        $batch = [];
        foreach ($posts as $post) {
            if ($post instanceof WP_Post) {
                $batch[] = $post;
            }
        }

        return $batch;
    }

    private static function deferTermCounting(bool $defer): void
    {
        if (function_exists('wp_defer_term_counting')) {
            wp_defer_term_counting($defer);
        }
    }

    /**
     * Drop what WordPress keeps in memory per query: the query log (kept
     * with SAVEQUERIES) and the object cache's in-memory copy.  A persistent
     * cache is never flushed.
     */
    private static function releaseMemory(): void
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (is_object($wpdb) && property_exists($wpdb, 'queries')) {
            $wpdb->queries = [];
        }

        if (function_exists('wp_cache_supports') && wp_cache_supports('flush_runtime')) {
            wp_cache_flush_runtime();

            return;
        }

        if (!wp_using_ext_object_cache()) {
            wp_cache_flush();

            return;
        }

        // A drop-in without `flush_runtime`: empty its in-memory copy, which
        // the common Memcached and Redis drop-ins keep in `cache`.
        $cache = $GLOBALS['wp_object_cache'] ?? null;
        if (is_object($cache) && property_exists($cache, 'cache')) {
            $cache->cache = [];
        }
    }
}
