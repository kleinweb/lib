<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

final class SiteContext
{
    private ?Site $initialized = null;

    private ?Site $active = null;

    private ?int $activeSiteId = null;

    public function __construct(
        private readonly SiteRepository $sites,
    ) {}
}
