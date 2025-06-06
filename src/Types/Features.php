<?php

// SPDX-FileCopyrightText: (C) 2023-2025 Alley <info@alley.com>
// SPDX-FileCopyrightText: (C) 2025 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Types;

/**
 * Describes multiple features.
 */
interface Features
{
    /**
     * Include a feature.
     *
     * @param Feature $feature feature to include
     */
    public function include(Feature $feature): void;
}
