<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Database\Batch;

/**
 * Where a batched walk over a table got to: the last ID it finished.
 */
interface Cursor
{
    public function get(): int;

    public function set(int $id): void;

    public function reset(): void;
}
