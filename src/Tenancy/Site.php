<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later


declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use League\Uri\Uri;

use function get_bloginfo;

final class Site
{
    public static function name(): string
    {
        return get_bloginfo('name');
    }

    public static function description(): string
    {
        return get_bloginfo('description');
    }

    public static function url(?int $siteId = null, string $path = ''): Uri
    {
        return Uri::new(get_home_url($siteId, $path));
    }

    public static function host(?int $siteId = null): ?string
    {
        return self::url($siteId)->getHost();
    }

    public static function isPrimaryHost(?string $url = null): bool
    {
        $homeUrl = self::url()->toString();

        return str_contains($url ?? $homeUrl, network_home_url());
    }
}
