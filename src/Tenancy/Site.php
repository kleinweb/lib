<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

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

    public static function url(?int $siteId = null, string $path = ''): string
    {
        return get_home_url($siteId, $path);
    }

    public static function fqdn(?int $siteId = null): string
    {
        return parse_url(self::url($siteId), PHP_URL_HOST);
    }

    public static function isPrimaryFqdn(?string $url = null): bool
    {
        return str_contains($url ?? self::url(), network_home_url());
    }
}
