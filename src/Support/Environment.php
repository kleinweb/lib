<?php

declare(strict_types=1);

namespace Kleinweb\Sitekit\Support;

final class Environment
{
    public const PRODUCTION = 'production';
    public const STAGING = 'staging';
    public const DEVELOPMENT = 'development';
    public const LOCAL = 'local';

    public static function isProduction(): bool
    {
        return wp_get_environment_type() === self::PRODUCTION;
    }
}
