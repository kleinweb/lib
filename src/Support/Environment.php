<?php

// SPDX-FileCopyrightText: (C) 2024-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

final class Environment
{
    public const string PRODUCTION = 'production';
    public const string MIGRATION = 'migration';
    public const string STAGING = 'staging';
    public const string DEVELOPMENT = 'development';
    public const string LOCAL = 'local';

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
