<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\ContentTypes;

/**
 * Registrations changed by {@see ContentTypeRegistrar::enter()}, to undo.
 *
 * @phpstan-import-type PostTypeDefinition from Definitions
 * @phpstan-import-type TaxonomyDefinition from Definitions
 *
 * @internal
 */
final class Changes
{
    /** @var list<string> */
    public array $addedPostTypes = [];

    /** @var array<string, array{definition: PostTypeDefinition, links: list<string>}> */
    public array $replacedPostTypes = [];

    /** @var array<string, array{definition: PostTypeDefinition, links: list<string>}> */
    public array $removedPostTypes = [];

    /** @var list<string> */
    public array $addedTaxonomies = [];

    /** @var array<string, array{definition: TaxonomyDefinition, objectTypes: list<string>}> */
    public array $replacedTaxonomies = [];

    /** @var array<string, array{definition: TaxonomyDefinition, objectTypes: list<string>}> */
    public array $removedTaxonomies = [];
}
