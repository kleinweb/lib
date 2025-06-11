<?php

// SPDX-FileCopyrightText: (C) 2022 Alley <mantle@alley.com>
// SPDX-FileCopyrightText: (C) 2024-2025 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Database\Contracts;

use Kleinweb\Lib\Database\Models\Model;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * Provides a way to normalize interacting with assorted WordPress objects
 * which have different properties. Allows for a uniform experience when
 * retrieving/updating object data in posts, terms, etc.
 *
 * @phpstan-consistent-constructor
 */
interface CoreObject
{
    /**
     * Getter for the entity ID.
     */
    public function id(): int;

    /**
     * Getter for the entity name.
     */
    public function name(): string;

    /**
     * Entity slug.
     */
    public function slug(): string;

    /**
     * Entity description.
     */
    public function description(): string;

    /**
     * Parent entity, if any.
     */
    public function parent(): ?Model;

    /**
     * Entity permalink, if any.
     */
    public function permalink(): ?string;

    /**
     * Core object for the model.
     *
     * FIXME: avoid needing to specify explicit classes (template needs a
     * param?)
     */
    public function getCoreObject(): WP_Post|WP_Term|WP_User;

    /**
     * Entity name or subtype.
     *
     * Examples include post type, taxonomy name, etc.
     */
    public static function getEntityName(): string;
}
