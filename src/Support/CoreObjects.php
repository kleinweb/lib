<?php

// SPDX-FileCopyrightText: (C) 2020-2023 Alley <mantle@alley.com>
// SPDX-FileCopyrightText: (C) 2023-2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later


declare(strict_types=1);

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
use function get_term;
use function get_term_by;
use function get_user_by;
use function get_userdata;

/**
 * Helpers for working with WordPress core objects.
 *
 * @phpstan-type CoreObjectType WP_Comment|WP_Post|WP_Site|WP_Term|WP_User|WP_Taxonomy
 */
final class CoreObjects
{
    /**
     * Whether the supplied value is a core post object.
     *
     * @param mixed $value value to test
     *
     * @phpstan-assert-if-true WP_Post $value
     */
    public static function isPost(mixed $value): bool
    {
        return $value instanceof WP_Post;
    }

    /**
     * Whether the supplied value is a core comment object.
     *
     * @param mixed $value value to test
     *
     * @phpstan-assert-if-true WP_Comment $value
     */
    public static function isComment(mixed $value): bool
    {
        return $value instanceof WP_Comment;
    }

    /**
     * Whether the supplied value is a core user object.
     *
     * @param mixed $value value to test
     *
     * @phpstan-assert-if-true WP_User $value
     */
    public static function isUser(mixed $value): bool
    {
        return $value instanceof WP_User;
    }

    /**
     * Whether the supplied value is a core site object.
     *
     * @param mixed $value value to test
     *
     * @phpstan-assert-if-true WP_Site $value
     */
    public static function isSite(mixed $value): bool
    {
        return $value instanceof WP_Site;
    }

    /**
     * Whether the supplied value is a core term object.
     *
     * @param mixed $value value to test
     *
     * @phpstan-assert-if-true WP_Term $value
     */
    public static function isTerm(mixed $value): bool
    {
        return $value instanceof WP_Term;
    }

    /**
     * Get a core post object.
     *
     * @param int|WP_Post|null $post Post ID or object. Default: global `$post` ID.
     */
    public static function getPost(int|WP_Post|null $post = null): ?WP_Post
    {
        $object = get_post($post);

        return self::isPost($object) ? $object : null;
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

        return self::isTerm($object) ? $object : null;
    }

    /**
     * Get a core term object by field value.
     *
     * @param 'slug'|'name'|'id'|'term_taxonomy_id' $field    field key
     * @param string|int                            $value    search for this term value
     * @param string                                $taxonomy Taxonomy name. Optional, if $field is 'term_taxonomy_id'.
     */
    public static function getTermBy(string $field, int|string $value, string $taxonomy = ''): ?WP_Term
    {
        $object = get_term_by($field, $value, $taxonomy, output: 'OBJECT', filter: 'raw');

        return self::isTerm($object) ? $object : null;
    }

    /**
     * Get a core comment object by ID.
     *
     * @param int $comment comment ID
     */
    public static function getComment(int $comment): ?WP_Comment
    {
        $object = get_comment($comment);

        return self::isComment($object) ? $object : null;
    }

    /**
     * Get a core user object by ID.
     *
     * @param int $user user ID
     */
    public static function getUser(int $user): ?WP_User
    {
        $object = get_userdata($user);

        return self::isUser($object) ? $object : null;
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

        return self::isUser($object) ? $object : null;
    }

    /**
     * Get a core site object.
     *
     * @param int|null $site Site to retrieve. Default: use the global site.
     */
    public static function getSite(?int $site = null): ?WP_Site
    {
        $object = get_site($site);

        return self::isSite($object) ? $object : null;
    }

    /**
     * Get a core taxonomy object by name.
     *
     * @param string $name taxonomy name
     */
    public static function getTaxonomy(string $name): ?WP_Taxonomy
    {
        return get_taxonomy($name) ?: null;
    }
}
