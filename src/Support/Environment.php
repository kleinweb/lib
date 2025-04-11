<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

final class Environment
{
    public const PRODUCTION = 'production';
    public const MIGRATION = 'migration';
    public const STAGING = 'staging';
    public const DEVELOPMENT = 'development';
    public const LOCAL = 'local';

    public static function isProduction(): bool
    {
        return wp_get_environment_type() === self::PRODUCTION;
    }

    public static function isMigration(): bool
    {
        return defined('WP_ENV')
            ? constant('WP_ENV') === self::MIGRATION
            : false;
    }
}
