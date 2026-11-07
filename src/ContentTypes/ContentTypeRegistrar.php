<?php

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

use Illuminate\Contracts\Config\Repository;

/**
 * Register custom post types and taxonomies from the `content-types` config.
 *
 * @phpstan-import-type PostTypeDefinition from Definitions
 * @phpstan-import-type TaxonomyDefinition from Definitions
 */
final class ContentTypeRegistrar
{
    /** @var array<array-key, PostTypeDefinition> registered by this class */
    private array $postTypes = [];

    /** @var array<array-key, TaxonomyDefinition> registered by this class */
    private array $taxonomies = [];

    public function __construct(private readonly Repository $config) {}

    public function definitions(): Definitions
    {
        return Definitions::fromConfig($this->config->get('content-types', []));
    }

    public function registerAll(): void
    {
        $definitions = $this->definitions();


    }
}
