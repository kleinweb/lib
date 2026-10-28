<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

use function in_array;
use function ksort;

/**
 * State behind the WordPress function stand-ins in `functions.php`.
 *
 * Only the behaviour the library relies on is modelled, after Core: the
 * switch stack and `switch_blog`, per-blog options with their filters,
 * blogmeta, a hook registry, and post type and taxonomy registration with
 * Core's linking rules (unregistering a post type unlinks its taxonomies).
 */
final class WordPress
{
    public static int $blogId = 1;

    /** @var array<int, array{domain: string, path: string, network: int}> */
    public static array $sites = [];

    /** @var array<int, array<string, mixed>> blog ID => meta key => value */
    public static array $meta = [];

    /** @var array<int, array<string, mixed>> blog ID => option => value */
    public static array $options = [];

    /** @var array<string, mixed> */
    public static array $networkOptions = [];

    /** @var array<string, array<int, list<callable>>> hook => priority => callbacks */
    public static array $hooks = [];

    /** @var array<string, int> */
    public static array $actionCounts = [];

    public static string $template = '';

    public static string $stylesheet = '';

    /** @var array<string, array{builtin: bool, args: array<array-key, mixed>}> */
    public static array $postTypes = [];

    /** @var array<string, array{object_type: list<string>, args: array<array-key, mixed>}> */
    public static array $taxonomies = [];

    public static function reset(): void
    {
        self::$blogId = 1;
        $GLOBALS['_wp_switched_stack'] = [];
        $GLOBALS['switched'] = false;
        self::$sites = [];
        self::$meta = [];
        self::$options = [];
        self::$networkOptions = [];
        self::$hooks = [];
        self::$actionCounts = ['init' => 1];
        self::$postTypes = [
            'post' => ['builtin' => true, 'args' => []],
            'page' => ['builtin' => true, 'args' => []],
        ];
        self::$taxonomies = [];
        self::$template = \constant('WP_CONTENT_DIR') . '/themes/kleinweb-base';
        self::$stylesheet = self::$template;
    }

    /**
     * Add a site, optionally keyed, with an active plugin list.
     *
     * @param list<string> $activePlugins
     */
    public static function addSite(int $id, ?string $key = null, array $activePlugins = []): void
    {
        self::$sites[$id] = ['domain' => "site{$id}.test", 'path' => '/', 'network' => 1];
        self::$meta[$id] = ($key !== null) ? ['site_key' => $key] : [];
        self::$options[$id] = ['active_plugins' => $activePlugins];
    }

    /**
     * Post types and taxonomies with sorted links, for comparing states.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function contentTypes(): array
    {
        $postTypes = self::$postTypes;
        $taxonomies = self::$taxonomies;
        ksort($postTypes);
        ksort($taxonomies);
        foreach (\array_keys($taxonomies) as $name) {
            $taxonomies[$name]['object_type'] = self::linkedTypes($name) ?? [];
        }

        return [$postTypes, $taxonomies];
    }

    /**
     * Object types a taxonomy is linked to, sorted, or null if unregistered.
     *
     * @return list<string>|null
     */
    public static function linkedTypes(string $taxonomy): ?array
    {
        $types = self::$taxonomies[$taxonomy]['object_type'] ?? null;
        if ($types === null) {
            return null;
        }

        \sort($types);

        return $types;
    }

    public static function link(string $taxonomy, string $type): void
    {
        if (!in_array($type, self::$taxonomies[$taxonomy]['object_type'], true)) {
            self::$taxonomies[$taxonomy]['object_type'][] = $type;
        }
    }
}
