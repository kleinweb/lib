<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

/**
 * The parts of `$wpdb` the library reads.
 */
final class FakeWpdb
{
    public string $posts = 'wp_posts';

    /** @var list<mixed> */
    public array $queries = [];
}
