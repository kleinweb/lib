<?php

// SPDX-FileCopyrightText: (C) 2024-2025 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Kleinweb\Lib\Support\Url;
use League\Uri\Uri;
use League\Uri\Components\Domain;

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

    public static function originalHost(?int $siteId = null): ?string
    {
        if (!is_multisite()) {
            return null;
        }

        $siteId ??= get_current_blog_id();
        $domain = get_site_meta($siteId, 'orig_host', single: true);

        return $domain ? Domain::new($domain)->toString() : null;
    }

    public static function isPrimaryHost(?int $siteId = null): bool
    {
        $host = Site::host($siteId);
        $primaryHost = Site::host(get_main_site_id());

        return is_multisite() ? $host === $primaryHost : true;
    }

    public static function isTempDomain(): bool
    {
        return Url::isKinstaDomain(self::url());
    }
}
