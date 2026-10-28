<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

use Kleinweb\Lib\Tenancy\Contracts\BlogBootstrapper;
use RuntimeException;

/**
 * A blog bootstrapper that throws on one blog.
 */
final class FailingOnBlog implements BlogBootstrapper
{
    public function __construct(private readonly int $blogId) {}

    public function apply(int $blogId): void
    {
        if ($blogId === $this->blogId) {
            throw new RuntimeException('boom');
        }
    }
}
