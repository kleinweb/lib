<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Stringable;
use WP_Site;

use function ltrim;
use function rtrim;
use function strtolower;

/**
 * Host and path that address a site, as stored in `wp_blogs`.
 */
final readonly class Domain implements Stringable
{
    public string $host;

    /** Path with leading and trailing slashes, e.g. `/` or `/newshub/`. */
    public string $path;

    public function __construct(string $host, string $path = '/')
    {
        $this->host = strtolower($host);
        $trimmed = rtrim(ltrim($path, '/'), '/');
        $this->path = ($trimmed === '') ? '/' : "/{$trimmed}/";
    }

    public static function fromSite(WP_Site $site): self
    {
        return new self($site->domain, $site->path);
    }

    public function isRootPath(): bool
    {
        return $this->path === '/';
    }

    public function equals(self $other): bool
    {
        return $this->host === $other->host && $this->path === $other->path;
    }

    public function __toString(): string
    {
        return $this->host . rtrim($this->path, '/');
    }
}
