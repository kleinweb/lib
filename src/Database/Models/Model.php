<?php

declare(strict_types=1);

namespace Kleinweb\Sitekit\Database\Models;

abstract class Model
{
    /**
     * Get the entity name.
     */
    abstract public static function getEntityName(): string;

    /**
     * Retrieve a model for an entity based on its identifier.
     *
     * @param T $identifier identifier
     *
     * @template T
     */
    abstract public static function find($identifier): ?static;

    /**
     * Create a new model instance for an existing entity.
     *
     * @param T $existing existing object or ID
     *
     * @template T
     */
    abstract public static function newFromExisting($existing): static;
}
