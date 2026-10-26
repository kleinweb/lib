<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;
use function md5;
use function serialize;

/**
 * Post type and taxonomy definitions from the `post-types` config.
 *
 * Same format as `roots/acorn-post-types`, so its config files work
 * unchanged:
 *
 * ```php
 * return [
 *     'post_types' => [
 *         'story' => ['menu_icon' => 'dashicons-media-document', 'names' => [...]],
 *     ],
 *     'taxonomies' => [
 *         'desk' => ['post_types' => ['story'], 'names' => [...]],
 *     ],
 * ];
 * ```
 *
 * Arguments are Extended CPTs arguments; `names` and, for taxonomies,
 * `post_types` are taken out of them.  An entry set to `null` (as a site
 * overlay does to drop a shared type) is skipped.
 *
 * @phpstan-type Names array{plural?: string, singular?: string, slug?: string}
 * @phpstan-type PostTypeDefinition array{args: array<array-key, mixed>, names: Names}
 * @phpstan-type TaxonomyDefinition array{objectTypes: list<string>, args: array<array-key, mixed>, names: Names}
 */
final readonly class Definitions
{
    /**
     * @param array<string, PostTypeDefinition> $postTypes
     * @param array<string, TaxonomyDefinition> $taxonomies
     */
    public function __construct(
        public array $postTypes,
        public array $taxonomies,
    ) {}

    public static function fromConfig(mixed $config): self
    {
        $config = is_array($config) ? $config : [];

        $postTypes = [];
        foreach (self::entries($config['post_types'] ?? []) as $name => $args) {
            $names = self::names($args);
            unset($args['names']);
            $postTypes[$name] = ['args' => $args, 'names' => $names];
        }

        $taxonomies = [];
        foreach (self::entries($config['taxonomies'] ?? []) as $name => $args) {
            $names = self::names($args);
            $objectTypes = array_values(array_filter(
                self::arrayAt($args, 'post_types'),
                is_string(...),
            ));
            unset($args['names'], $args['post_types']);
            $taxonomies[$name] = [
                'objectTypes' => $objectTypes,
                'args' => $args,
                'names' => $names,
            ];
        }

        return new self($postTypes, $taxonomies);
    }

    /**
     * Fingerprint of one definition, to tell whether two sites differ.
     *
     * @param PostTypeDefinition|TaxonomyDefinition $definition
     */
    public static function hash(array $definition): string
    {
        return md5(serialize($definition));
    }

    /**
     * @return array<string, array<array-key, mixed>>
     */
    private static function entries(mixed $entries): array
    {
        $valid = [];
        foreach (is_array($entries) ? $entries : [] as $name => $args) {
            if (is_string($name) && is_array($args)) {
                $valid[$name] = $args;
            }
        }

        return $valid;
    }

    /**
     * The `names` argument, keeping only the keys Extended CPTs reads.
     *
     * @param array<array-key, mixed> $args
     *
     * @return Names
     */
    private static function names(array $args): array
    {
        $given = self::arrayAt($args, 'names');
        $names = [];

        foreach (['plural', 'singular', 'slug'] as $key) {
            if (is_string($given[$key] ?? null)) {
                $names[$key] = $given[$key];
            }
        }

        return $names;
    }

    /**
     * @param array<array-key, mixed> $args
     *
     * @return array<array-key, mixed>
     */
    private static function arrayAt(array $args, string $key): array
    {
        $value = $args[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
