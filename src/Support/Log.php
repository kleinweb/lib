<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later


declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Illuminate\Support\Facades;
use WP_Error;

final class Log
{
    /**
     * Log each error message in a {@link \WP_Error} object.
     *
     * @param WP_Error $error core error instance
     */
    public static function fromCoreError(WP_Error $error): void
    {
        foreach ($error->get_error_messages() as $msg) {
            Facades\Log::error(
                $msg,
                ['wp_error' => $error],
            );
        }
    }
}
