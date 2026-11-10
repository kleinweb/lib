<?php

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

/**
 * Post type and taxonomy definitions from the `content-types` config.
 *
 * TODO: Use types from <johnbillion/extended-cpts>?
 *
 * @phpstan-type Names array{plural?: string, singular?: string, slug?: string}
 * @phpstan-type PostTypeDefinition array{args: array<string, mixed>, names: Names}
 * @phpstan-type TaxonomyDefinition array{objectTypes: string[], args: array<string, mixed>, names: Names}
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
        $taxonomies = [];

        foreach (self::entries($config['post_types'] ?? []) as $name => $args) {
            $names = self::names($args);

            unset($args['names']);

            $postTypes[$name] = compact('args', 'names');
        }

        foreach (self::entries($config['taxonomies'] ?? []) as $name => $args) {
            $names = self::names($args);
            $objectTypes = array_values(array_filter(
                self::arrayAt($args, 'post_types'),
                is_string(...),
            ));

            unset($args['names'], $args['post_types']);

            $taxonomies[$name] = compact('args', 'names', 'objectTypes');
        }

        return new self($postTypes, $taxonomies);
    }

    /**
     * @param PostTypeDefinition|TaxonomyDefinition $definition
     */
    public static function hash(array $definition): string
    {
        return md5(serialize($definition));
    }

    /**
     * TODO: is this not a common utility pattern?
     *
     * @return array<string, array<string, mixed>>
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
     * The `names` argument with only the relevant keys.
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
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    private static function arrayAt(array $args, string $key): array
    {
        $value = $args[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
