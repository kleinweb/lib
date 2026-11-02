<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Database\Batch;

/**
 * A cursor for one run only: every run starts from the beginning.
 */
final class MemoryCursor implements Cursor
{
    public function __construct(private int $id = 0) {}

    public function get(): int
    {
        return $this->id;
    }

    public function set(int $id): void
    {
        $this->id = $id;
    }

    public function reset(): void
    {
        $this->id = 0;
    }
}
