<?php

declare(strict_types=1);

namespace Kleinweb\Lib\View\Support;

use Assert\Assertion;

use function get_option;
use function sprintf;

/**
 * Helpers for working with dates and probably also times.
 */
final class Date
{
    /**
     * WordPress core uses a datetime format based on MySQL's DATETIME for some
     * internal values.
     *
     * @see https://dev.mysql.com/doc/refman/8.0/en/datetime.html
     */
    public const WP_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Get the datetime format as defined by site settings.
     */
    public static function getSiteDateTimeFormat(): string
    {
        $dateFormat = get_option('date_format') ?: '';
        Assertion::string($dateFormat);
        Assertion::notEmpty($dateFormat);

        $timeFormat = get_option('time_format') ?: '';
        Assertion::string($timeFormat);
        Assertion::notEmpty($timeFormat);

        return sprintf('%s %s', $dateFormat, $timeFormat);
    }
}
