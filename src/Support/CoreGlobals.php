<?php

// SPDX-FileCopyrightText: (C) 2020-2023 Alley <mantle@alley.com>
// SPDX-FileCopyrightText: (C) 2023-2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later


declare(strict_types=1);

// We are not in camel territory here, this is WordPress core.
// phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps

namespace Kleinweb\Lib\Support;

use Webmozart\Assert\Assert;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Term;
use WP_User;

use function get_queried_object;
use function gettype;
use function sprintf;

/**
 * Helper function for safely reading from WordPress core global variables.
 */
final class CoreGlobals
{
    /**
     * Get the global query object.
     *
     * The underlying {@link $wp_the_query} global variable is set very early
     * in the WordPress bootstrapping process as a copy of the
     * slightly-less-reliable {@link $wp_query}.
     *
     * If, somehow, this function fails to return a {@link WP_Query},
     * thus resulting in a fatal error, then something was extremely wrong
     * before this function was called.
     *
     * A fatal error, in that case, is quite appropriate.
     *
     * @see https://github.com/WordPress/WordPress/blob/84e9601e5a2966c0aa80020bbf0c043dd8b6bfbb/wp-settings.php#L500-L515
     */
    public static function query(): WP_Query
    {
        $globalQuery = $GLOBALS['wp_the_query'];

        Assert::isInstanceOf(
            $globalQuery,
            WP_Query::class,
            // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
            'The WordPress global "$wp_the_query" is unusable. This request is beyond saving. Goodbye.',
        );

        return $globalQuery;
    }

    /**
     * Get the global post object, if any.
     *
     * Unlike the `$wp_query` global variable, `$post` may or may not exist.
     *
     * If available, it must always be a {@link WP_Post}, otherwise an error is
     * appropriate since we can't rely on anything working properly.
     */
    public static function post(): ?WP_Post
    {
        $globalPost = $GLOBALS['post'] ?? null;
        if (! $globalPost) {
            return null;
        }

        Assert::isInstanceOf(
            $globalPost,
            WP_Post::class,
            sprintf(
                // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
                'The WordPress global "$post" is not of the expected type. Expected "WP_Post", got "%s".',
                gettype($globalPost),
            ),
        );

        return $globalPost;
    }

    /**
     * @return WP_Post[]|int[]
     */
    public static function posts(): array
    {
        $mainQuery = self::query();
        if (! $mainQuery->posts) {
            return [];
        }

        return $mainQuery->posts;
    }

    /**
     * Get the global queried object, if any.
     *
     * This function is a simple proxy to {@link get_queried_object()}.
     *
     * @see {@link get_queried_object()}
     */
    public static function queried(): WP_Post|WP_Post_Type|WP_Term|WP_User|null
    {
        return get_queried_object();
    }
}
