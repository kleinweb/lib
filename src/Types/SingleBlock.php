<?php

// SPDX-FileCopyrightText: (C) 2023-2025 Alley <info@alley.com>
// SPDX-FileCopyrightText: (C) 2025 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Types;

/**
 * Describes a single block.
 */
interface SingleBlock extends SerializedBlocks
{
    /**
     * Block name.
     */
    public function blockName(): ?string;

    /**
     * Parsed block.
     *
     * @phpstan-return array{
     *     blockName: ?string,
     *     attrs: array<string, mixed>,
     *     innerBlocks: array<mixed[]>,
     *     innerHTML: string,
     *     innerContent: string[]
     * }
     */
    public function parsedBlock(): array;
}
