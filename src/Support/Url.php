<?php

// SPDX-FileCopyrightText: (C) 2024-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use League\Uri\Components\HierarchicalPath;
use League\Uri\Components\Host;
use League\Uri\Components\Path;
use League\Uri\Contracts\UriInterface;
use League\Uri\Exceptions\SyntaxError as UriSyntaxError;
use League\Uri\Uri;
use Webmozart\Assert\Assert;

use function home_url;
use function is_multisite;
use function network_home_url;

final class Url
{
    /**
     * Generate a URL for a filesystem path relative to the webroot.
     *
     * @param string $path filesystem path, either absolute or relative to webroot
     *
     * @throws BindingResolutionException
     */
    public static function fromFilesystemPath(string $path): UriInterface
    {
        $uri = Uri::new(is_multisite() ? network_home_url() : home_url());
        $path = HierarchicalPath::new($path)
            ->withoutDotSegments();

        if (!$path->isAbsolute()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new UriSyntaxError('Target path must be absolute: ' . $path->value());
        }

        $webRoot = \app()->webRoot();
        $webRoot = HierarchicalPath::new($webRoot)
            ->withoutDotSegments();

        Assert::stringNotEmpty($path->value());
        Assert::stringNotEmpty($webRoot->value());

        // Strip the dirname common to webroot and target.
        $relativePath = Str::after($path->value(), $webRoot->value());

        // A filesystem path in the web root is an absolute URI path.
        $uriPath = Path::new($relativePath)->withLeadingSlash();

        return $uri->withPath($uriPath);
    }

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

    public static function isKinstaDomain(string|Uri $uri): bool
    {
        $host = Uri::new($uri)->getHost();

        return $host && Str::endsWith($host, 'kinsta.cloud');
    }
}
