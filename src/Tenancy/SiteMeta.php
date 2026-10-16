<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

/**
 * Site meta keys used for multisite tenancy.
 *
 * Stored as unprefixed blogmeta, following the keys first written by
 * {@see \Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains}.
 */
enum SiteMeta: string
{
    /** Stable, environment-independent identifier for the site. */
    case Key = 'site_key';

    /** Hostname before the site's domain was demapped. */
    case OriginalHost = 'orig_host';

    /** Path before the site's domain was demapped. */
    case OriginalPath = 'orig_path';

    /** Site URL before the site's domain was demapped. */
    case OriginalSiteUrl = 'orig_siteurl';
}
