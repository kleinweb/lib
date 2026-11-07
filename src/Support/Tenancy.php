<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

final class Tenancy
{
    public static function isMultisite(): bool
    {
        return function_exists('is_multisite') && is_multisite();
    }
}
