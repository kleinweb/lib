<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

use Closure;
use ExtCPTs\PostType;
use ExtCPTs\Taxonomy;
use Illuminate\Contracts\Config\Repository;
use Kleinweb\Lib\Database\Models\Site;

use function array_reverse;
use function array_values;
use function class_exists;
use function did_action;
use function function_exists;
use function get_object_taxonomies;
use function get_taxonomy;
use function post_type_exists;
use function register_extended_post_type;
use function register_extended_taxonomy;
use function register_post_type;
use function register_taxonomy;
use function register_taxonomy_for_object_type;
use function strtolower;
use function taxonomy_exists;
use function unregister_post_type;
use function unregister_taxonomy;

/**
 * Registers post types and taxonomies from the `post-types` config.
 *
 * On `init`, the definitions for the site WordPress loaded are registered
 * with Extended CPTs, with all its admin and query features.  Site plugins
 * add, change or remove types by overlaying `post-types` from their
 * `config/post-types.php`.
 *
 * Like Core, registration does not follow `switch_to_blog()` on its own:
 * Core's internal switches (the admin bar's site list, `get_home_url()`)
 * leave post types alone.  Code that works with another site's content, such
 * as a console command looping over sites, asks for it explicitly with
 * {@see ContentTypeRegistrar::runFor()}: while it runs, the target site's
 * types that are missing are registered, those defined differently are
 * re-registered with the target's arguments, and those the target does not
 * have are unregistered.  Everything is put back afterwards, taxonomy links
 * included.  Those temporary registrations use Core's functions with the
 * arguments Extended CPTs computes (labels, rewrite slugs), without its admin
 * and query hooks.  Types registered by anything else are never touched.
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
        return Definitions::fromConfig($this->config->get('post-types', []));
    }

    /**
     * Register the current site's definitions.  Runs on `init`.
     */
    public function registerAll(): void
    {
        $definitions = $this->definitions();

        foreach ($definitions->postTypes as $name => $definition) {
            if (function_exists('register_extended_post_type')) {
                register_extended_post_type($name, $definition['args'], $definition['names']);
            } else {
                self::registerPostType($name, $definition, []);
            }

            $this->postTypes[$name] = $definition;
        }

        foreach ($definitions->taxonomies as $name => $definition) {
            if (function_exists('register_extended_taxonomy')) {
                register_extended_taxonomy(
                    $name,
                    $definition['objectTypes'],
                    $definition['args'],
                    $definition['names'],
                );
            } else {
                self::registerTaxonomy($name, $definition, $definition['objectTypes']);
            }

            $this->taxonomies[$name] = $definition;
        }
    }

    /**
     * Run a callback on a site with that site's post types and taxonomies.
     *
     * @template TReturn
     *
     * @param Closure(Site): TReturn $callback
     *
     * @return TReturn
     */
    public function runFor(Site $site, Closure $callback): mixed
    {
        return $site->run(function (Site $site) use ($callback): mixed {
            $changes = $this->enter();

            try {
                return $callback($site);
            } finally {
                $this->leave($changes);
            }
        });
    }

    /**
     * Bring registrations in line with the current blog's definitions.
     *
     * Call after `switch_to_blog()`; pass the result to
     * {@see ContentTypeRegistrar::leave()} before restoring.  Only types this
     * class registered are replaced or removed.
     */
    public function enter(): Changes
    {
        $changes = new Changes();
        if (!did_action('init')) {
            return $changes;
        }

        $target = $this->definitions();

        foreach ($target->postTypes as $name => $definition) {
            $current = $this->postTypes[$name] ?? null;

            if ($current === null) {
                if (post_type_exists($name)) {
                    // Registered by something else.
                    continue;
                }

                $changes->addedPostTypes[] = $name;
                self::registerPostType($name, $definition, []);
            } elseif (Definitions::hash($current) !== Definitions::hash($definition)) {
                $links = self::unregisterPostType($name);
                $changes->replacedPostTypes[$name] = ['definition' => $current, 'links' => $links];
                self::registerPostType($name, $definition, $links);
            } else {
                continue;
            }

            $this->postTypes[$name] = $definition;
        }

        foreach ($this->postTypes as $name => $definition) {
            if (isset($target->postTypes[$name])) {
                continue;
            }

            $changes->removedPostTypes[$name] = [
                'definition' => $definition,
                'links' => self::unregisterPostType($name),
            ];
            unset($this->postTypes[$name]);
        }

        foreach ($target->taxonomies as $name => $definition) {
            $current = $this->taxonomies[$name] ?? null;

            if ($current === null) {
                if (taxonomy_exists($name)) {
                    continue;
                }

                $changes->addedTaxonomies[] = $name;
            } elseif (Definitions::hash($current) !== Definitions::hash($definition)) {
                $changes->replacedTaxonomies[$name] = [
                    'definition' => $current,
                    'objectTypes' => self::unregisterTaxonomy($name),
                ];
            } else {
                continue;
            }

            self::registerTaxonomy($name, $definition, $definition['objectTypes']);
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
     * Undo the changes {@see ContentTypeRegistrar::enter()} made.
     *
     * Restores each type with the taxonomy links it had before, including
     * links added by other code.  Taxonomies come back first: re-registering
     * a post type relinks it to every taxonomy it had, which a taxonomy's
     * own record may lack if the post type was unregistered before it.
     */
    public function leave(Changes $changes): void
    {
        foreach (array_reverse($changes->addedTaxonomies) as $name) {
            unregister_taxonomy($name);
            unset($this->taxonomies[$name]);
        }

        $taxonomies = $changes->replacedTaxonomies + $changes->removedTaxonomies;
        foreach ($taxonomies as $name => $taxonomy) {
            if (taxonomy_exists($name)) {
                unregister_taxonomy($name);
            }

            self::registerTaxonomy($name, $taxonomy['definition'], $taxonomy['objectTypes']);
            $this->taxonomies[$name] = $taxonomy['definition'];
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

            self::registerPostType($name, $definition, $links);
            $this->postTypes[$name] = $definition;
        }
    }

    /**
     * Unregister a post type, returning the taxonomies linked to it, which
     * Core unlinks as part of unregistering.
     *
     * @return list<string>
     */
    private static function unregisterPostType(string $name): array
    {
        $links = array_values(get_object_taxonomies($name));
        unregister_post_type($name);

        return $links;
    }

    /**
     * Unregister a taxonomy, returning the object types it was linked to.
     *
     * @return list<string>
     */
    private static function unregisterTaxonomy(string $name): array
    {
        $taxonomy = get_taxonomy($name);
        $objectTypes = ($taxonomy !== false) ? array_values($taxonomy->object_type) : [];
        unregister_taxonomy($name);

        return $objectTypes;
    }

    /**
     * @param PostTypeDefinition $definition
     * @param list<string>       $links      taxonomies to link the post type to
     */
    private static function registerPostType(string $name, array $definition, array $links): void
    {
        $args = class_exists(PostType::class)
            ? (new PostType($name, $definition['args'], $definition['names']))->args
            : $definition['args'];

        $name = strtolower($name);
        if ($name === '') {
            return;
        }

        // @phpstan-ignore argument.type (Arguments come from config; WordPress validates them.)
        register_post_type($name, $args);

        foreach ($links as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                register_taxonomy_for_object_type($taxonomy, $name);
            }
        }
    }

    /**
     * @param TaxonomyDefinition $definition
     * @param list<string>       $objectTypes
     */
    private static function registerTaxonomy(
        string $name,
        array $definition,
        array $objectTypes,
    ): void {
        $args = class_exists(Taxonomy::class)
            ? (new Taxonomy(
                $name,
                $definition['objectTypes'],
                $definition['args'],
                $definition['names'],
            ))->args
            : $definition['args'];

        // @phpstan-ignore argument.type (Arguments come from config; WordPress validates them.)
        register_taxonomy($name, $objectTypes, $args);
    }
}
