<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-FileCopyrightText: (C) spatie
// SPDX-License-Identifier: GPL-3.0-or-later OR MIT


declare(strict_types=1);

namespace Kleinweb\Lib\Package\Exceptions;

use Exception;

final class InvalidPackage extends Exception
{
    public static function nameIsRequired(): self
    {
        // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
        return new static('This package does not have a name. You can set one with `$package->name("yourName")`');
    }
}
