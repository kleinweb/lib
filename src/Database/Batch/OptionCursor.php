<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Database\Batch;

use function delete_option;
use function get_option;
use function is_numeric;
use function update_option;

/**
 * A cursor kept in a WordPress option, so an interrupted run resumes.
 *
 * Options are per blog, so on multisite each site has its own cursor, and
 * the cursor follows `switch_to_blog()`.  An option rather than the cache:
 * a cache entry can be evicted, which would silently restart a long run.
 */
final readonly class OptionCursor implements Cursor
{
    public function __construct(public string $option) {}

    public function get(): int
    {
        $value = get_option($this->option, 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    public function set(int $id): void
    {
        update_option($this->option, $id, false);
    }

    public function reset(): void
    {
        delete_option($this->option);
    }
}
