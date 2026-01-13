<?php

// SPDX-FileCopyrightText: (C) 2025-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Exceptions;

use Exception;

final class MultisiteOnly extends Exception
{
    public function __construct(string $message = 'Multisite only.')
    {
        parent::__construct($message);
    }
}
