<?php

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

    /** @deprecated Use {@link \Kleinweb\Lib\Tenancy\Site::host()}. */
    public static function fqdn(?int $siteId = null): ?string
    {
        return self::host($siteId);
    }

    public static function isPrimaryFqdn(?string $url = null): bool
    {
        return str_contains($url ?? self::url()->toString(), network_home_url());
    }
}
