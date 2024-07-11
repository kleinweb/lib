<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use function array_filter;
use function get_fields;
use function is_array;

/**
 * Helpers for working with ACF data.
 */
final class Acf
{
    /**
     * @return mixed[]
     */
    public static function getFields(string $name): array
    {
        return get_fields($name) ?: [];
    }

    /**
     * Ensure an array of non-falsy values.
     *
     * @return mixed[]
     */
    public static function normaliseItems(mixed $items): array
    {
        return is_array($items) ? array_filter($items) : [];
    }
}
