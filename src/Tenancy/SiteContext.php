<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Closure;
use Illuminate\Events\Dispatcher;
use Kleinweb\Lib\Database\Models\Site;

/**
 * Synchronize the application and the current WordPress multisite site.
 *
 * Site context includes the initialized site and the active site.
 *
 * The initialized site is the first site WordPress loaded for this
 * request or process.  The active site follows every {@see
 * switch_to_blog()} and {@see restore_current_blog()}.
 *
 * FIXME: Any exception thrown within would leave the caller stuck in
 *        the current site context.  This could be catastrophic?
 */
final class SiteContext
{
    private ?Site $initialized = null;

    private ?Site $active = null;

    private ?int $activeSiteId = null;

    public function __construct(
        private readonly SiteRepository $sites,
        private readonly Dispatcher $events,
        private readonly array $siteBootstrappers,
        private readonly array $tenantBootstrappers,
        private readonly Closure $report,
    ) {}
}
