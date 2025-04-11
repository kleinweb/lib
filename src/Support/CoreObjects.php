<?php

// SPDX-FileCopyrightText: (C) 2020-2023 Alley <mantle@alley.com>
// SPDX-FileCopyrightText: (C) 2023-2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

/**
 * Helpers for working with WordPress core objects.
 *
 * @phpstan-type CoreObjectType WP_Comment|WP_Post|WP_Site|WP_Term|WP_User|WP_Taxonomy
 */

namespace Kleinweb\Lib\Support;

use WP_Comment;
use WP_Post;
use WP_Site;
use WP_Taxonomy;
use WP_Term;
use WP_User;

use function get_comment;
use function get_post;
use function get_site;
use function get_taxonomy;
use function get_term;
use function get_term_by;
use function get_user_by;
use function get_userdata;

final class CoreObjects
{
    /**
     * Get a core post object.
     *
     * @param int|WP_Post|null $post Post ID or object. Default: global `$post` ID.
     */
    public static function getPost(int|WP_Post|null $post = null): ?WP_Post
    {
        $object = get_post($post);

        return ($object instanceof WP_Post) ? $object : null;
    }

    /**
     * Get a core term object.
     *
     * @param object|int $term     term ID, database object, or term object
     * @param string     $taxonomy taxonomy name
     */
    public static function getTerm(int|object $term, string $taxonomy = ''): ?WP_Term
    {
        $object = get_term($term, $taxonomy);

        return ($object instanceof WP_Term) ? $object : null;
    }

    /**
     * Get a core term object by field value.
     *
     * @param 'slug'|'name'|'id'|'term_taxonomy_id' $field    field key
     * @param string|int                            $value    search for this term value
     * @param string                                $taxonomy Taxonomy name. Optional, if $field is 'term_taxonomy_id'.
     */
    public static function getTermBy(
        string $field,
        int|string $value,
        string $taxonomy = '',
    ): ?WP_Term {
        $object = get_term_by($field, $value, $taxonomy, output: 'OBJECT', filter: 'raw');

        return ($object instanceof WP_Term) ? $object : null;
    }

    /**
     * Get a core comment object.
     */
    public static function getComment(WP_Comment|int $comment): ?WP_Comment
    {
        $object = get_comment($comment);

        return ($object instanceof WP_Comment) ? $object : null;
    }

    /**
     * Get a core user object.
     */
    public static function getUser(WP_User|int $user): ?WP_User
    {
        if ($user instanceof WP_User) {
            return $user;
        }

        $object = get_userdata($user);

        return ($object instanceof WP_User) ? $object : null;
    }

    /**
     * Get a core user object by field value.
     *
     * @param 'id'|'slug'|'email'|'login' $field field key
     * @param int|string                  $value search for this user value
     */
    public static function getUserBy(string $field, int|string $value): ?WP_User
    {
        $object = get_user_by($field, $value);

        return ($object instanceof WP_User) ? $object : null;
    }

    /**
     * Get a core site object.
     *
     * Defaults to the current site when {@link $site} is null.
     */
    public static function getSite(WP_Site|int|null $site = null): ?WP_Site
    {
        $object = get_site($site);

        return ($object instanceof WP_Site) ? $object : null;
    }

    /**
     * Get a core taxonomy object by name.
     *
     * Nullable wrapper for {@link get_taxonomy()}.
     *
     * @param string $name taxonomy name
     */
    public static function getTaxonomy(string $name): ?WP_Taxonomy
    {
        return get_taxonomy($name) ?: null;
    }
}
