<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Exceptions;

use InvalidArgumentException;

final class InvalidSiteKey extends InvalidArgumentException
{
    public static function forValue(string $value): self
    {
        return new self(sprintf(
            'Invalid site key "%s": a site key must be a lowercase DNS label '
            . '(letters, digits and hyphens; 1-63 characters; no leading or trailing hyphen).',
            $value,
        ));
    }

    public static function notNamespaceable(string $value): self
    {
        return new self(sprintf(
            'Site key "%s" starts with a digit and cannot be used as a PHP namespace segment.',
            $value,
        ));
    }
}
