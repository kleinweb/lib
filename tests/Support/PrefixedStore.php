<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

use Illuminate\Cache\NullStore;

/**
 * A cache store that keeps a prefix, standing in for Redis or Memcached.
 */
final class PrefixedStore extends NullStore
{
    private string $prefix = '';

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
