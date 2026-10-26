<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

/**
 * Site meta keys used for multisite tenancy.
 */
enum SiteMeta: string
{
    /** Stable identifier for a site. */
    case Key = 'site_key';

    /** Hostname before the site's domain was de-mapped. */
    case OriginalHost = 'orig_host';

    /** Path before the site's domain was de-mapped. */
    case OriginalPath = 'orig_path';

    /** Site URL before the site's domain was de-mapped. */
    case OriginalSiteUrl = 'orig_siteurl';
}
