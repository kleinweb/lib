<?php

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

use ExtCPTs\Taxonomy;
use Illuminate\Contracts\Config\Repository;
use Kleinweb\Lib\Database\Models\Site;
use Closure;

/**
 * Register custom post types and taxonomies from the `content-types` config.
 *
 * FIXME: this class should be refactored prior to release
 *
 * @phpstan-import-type PostTypeDefinition from Definitions
 * @phpstan-import-type TaxonomyDefinition from Definitions
 */
final class ContentTypeRegistrar
{
    /** @var array<string, PostTypeDefinition> registered by this class */
    private array $postTypes = [];

    /** @var array<string, TaxonomyDefinition> registered by this class */
    private array $taxonomies = [];

    public function __construct(private readonly Repository $config) {}

    public function definitions(): Definitions
    {
        return Definitions::fromConfig($this->config->get('content-types', []));
    }

    public function registerAll(): void
    {
        $this->registerPostTypes();
        $this->registerTaxonomies();
    }

    /**
     * Invoke a callback in a site-specific context.
     *
     * FIXME: move to tenancy namespace, or something...?  or maybe just a rename
     *
     * @template TReturn
     *
     * @param Closure(Site): TReturn $callback
     *
     * @return TReturn
     */
    public function invokeWithSite(Site $site, Closure $callback): mixed
    {
        return $site->invokeWithSite(function (Site $site) use ($callback): mixed {
            $changes = $this->enter();

            try {
                return $callback($site);
            } finally {
                $this->leave($changes);
            }
        });
    }

    /**
     * Sync content type registrations with the current site's configuration.
     *
     * This method is intended for invocation after {@see switch_to_blog()}.  Forward the result to {@see ContentTypeRegistrar::leave()} before restoring the previous site.
     *
     * Affects only the content type registrations handled by this class.
     *
     * TODO: refactor smells
     */
    public function enter(): Changes
    {
        $changes = new Changes();

        // TODO: add explanatory comment -- what prompted this?
        if (!did_action('init')) {
            return $changes;
        }

        $target = $this->definitions();

        foreach ($target->postTypes as $name => $definition) {
            $stored = $this->postTypes[$name] ?? null;

            if ($stored === null) {
                if (post_type_exists($name)) {
                    // Someone else already registered this post type.
                    // TODO: throw?
                    continue;
                }

                $changes->addedPostTypes[] = $name;
            } elseif (Definitions::hash($stored) !== Definitions::hash($definition)) {
                $links = self::unregisterPostType($name);

                // Record the stored definition to be replaced.
                // FIXME: should not know the required shape.  use a setter on $changes instead.
                $changes->replacedPostTypes[$name] = [
                    'definition' => $stored,
                    'links' => $links,
                ];
            } else {
                continue;
            }

            self::registerPostType($name, $definition);

            $this->postTypes[$name] = $definition;
        }

        foreach ($this->postTypes as $name => $definition) {
            if (isset($target->postTypes[$name])) {
                continue;
            }

            $links = self::unregisterPostType($name);

            $changes->removedPostTypes[$name] = compact(
                'definition',
                'links',
            );

            unset($this->postTypes[$name]);
        }

        foreach ($target->taxonomies as $name => $definition) {
            $stored = $this->taxonomies[$name] ?? null;

            if ($stored === null) {
                if (taxonomy_exists($name)) {
                    // Someone else already registered this taxonomy.
                    // TODO: throw?
                    continue;
                }

                $changes->addedTaxonomies[] = $name;
            } elseif (Definitions::hash($stored) !== Definitions::hash($definition)) {
                $changes->replacedTaxonomies[$name] = [
                    'definition' => $stored,
                    'objectTypes' => self::unregisterTaxonomy($name),
                ];
            } else {
                continue;
            }

            self::registerTaxonomy($name, $definition);

            $this->taxonomies[$name] = $definition;
        }

        foreach ($this->taxonomies as $name => $definition) {
            if (isset($target->taxonomies[$name])) {
                continue;
            }

            $changes->removedTaxonomies[$name] = [
                'definition' => $definition,
                'objectTypes' => self::unregisterTaxonomy($name),
            ];

            unset($this->taxonomies[$name]);
        }

        return $changes;
    }

    /**
     * Reverse the effects of {@see ContentTypeRegistrar::enter()}.
     *
     * Restores each content type and their respective links, including those links added by other code.
     */
    public function leave(Changes $changes): void
    {
        foreach (array_reverse($changes->addedTaxonomies) as $name) {
            unregister_taxonomy($name);

            unset($this->taxonomies[$name]);
        }

        $taxonomies = $changes->replacedTaxonomies + $changes->removedTaxonomies;

        foreach ($taxonomies as $name => ['definition' => $definition, 'objectTypes' => $objectTypes]) {
            if (taxonomy_exists($name)) {
                unregister_taxonomy($name);
            }

            self::registerTaxonomy($name, $definition, $objectTypes);

            $this->taxonomies[$name] = $definition;
        }

        foreach ($changes->addedPostTypes as $name) {
            unregister_post_type($name);

            unset($this->postTypes[$name]);
        }

        $postTypes = $changes->replacedPostTypes + $changes->removedPostTypes;
        foreach ($postTypes as $name => ['definition' => $definition, 'links' => $links]) {
            if (post_type_exists($name)) {
                unregister_post_type($name);
            }

            self::registerPostType($name, $definition);

            $this->postTypes[$name] = $definition;
        }
    }

    private function registerPostTypes(): void
    {
        $definitions = $this->definitions();

        foreach ($definitions->postTypes as $name => $definition) {
            register_extended_post_type($name, $definition['args'], $definition['names']);

            $this->postTypes[$name] = $definition;
        }
    }

    private function registerTaxonomies(): void
    {
        $definitions = $this->definitions();

        foreach ($definitions->taxonomies as $name => $definition) {
            register_extended_taxonomy(
                $name,
                $definition['objectTypes'],
                $definition['args'],
                $definition['names'],
            );

            $this->taxonomies[$name] = $definition;
        }
    }

    /**
     * @param PostTypeDefinition $definition
     */
    private static function registerPostType(string $name, array $definition): void
    {
        register_extended_post_type($name, $definition['args'], $definition['names']);
    }

    /**
     * Unregister a post type, returning any taxonomies to which it was linked.
     *
     * @return string[]
     */
    private static function unregisterPostType(string $name): array
    {
        $links = array_values(get_object_taxonomies($name));

        unregister_post_type($name);

        return $links;
    }

    /**
     * @param TaxonomyDefinition $definition
     */
    private static function registerTaxonomy(string $name, array $definition): void
    {
        register_extended_taxonomy(
            $name,
            $definition['objectTypes'],
            $definition['args'],
            $definition['names'],
        );
    }

    /**
     * Unregister a taxonomy, returning any object types to which it was linked.
     *
     * @return string[]
     */
    private static function unregisterTaxonomy(string $name): array
    {
        $taxonomy = get_taxonomy($name);
        $objectTypes = ($taxonomy !== false) ? array_values($taxonomy->object_type) : [];

        unregister_taxonomy($name);

        return $objectTypes;
    }
}
