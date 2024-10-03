<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later


declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Illuminate\Support\Facades\Request;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Uri;

final class Url
{
    /**
     * Determine whether the provided URI shares the same RFC3986 Host
     * as the currently-active request.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2.2
     */
    public static function isCurrentHost(string|Uri $uri): bool
    {
        $uri = Uri::new($uri);
        $path = Path::fromUri($uri);

        if ($path->isAbsolute()) {
            return true;
        }

        $host = Host::fromUri($uri);
        $currentHost = Host::new(Request::host());

        return $host->value() === $currentHost->value();
    }
}
