<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Contracts\Events\Dispatcher;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Contracts\SiteBootstrapper;
use Kleinweb\Lib\Tenancy\Events\SiteInitialized;
use Kleinweb\Lib\Tenancy\Events\SiteSwitched;

use function do_action;

/**
 * Tracks which site the application is set up for.
 *
 * The *initialized* site is the one WordPress loaded for the request; its
 * providers are registered once and never change.  The *active* site follows
 * `switch_to_blog()`: bootstrappers (such as the config overlay) are reverted
 * and re-applied for it on every switch.
 */
final class SiteContext
{
    private ?Site $initialized = null;

    private ?Site $active = null;

    /**
     * @param list<SiteBootstrapper> $bootstrappers
     */
    public function __construct(
        private readonly SiteRepository $sites,
        private readonly Dispatcher $events,
        private readonly array $bootstrappers,
    ) {}

    /**
     * The site whose providers were registered for this request.
     */
    public function initialized(): ?Site
    {
        return $this->initialized;
    }

    /**
     * The keyed site bootstrappers are currently applied for.
     */
    public function active(): ?Site
    {
        return $this->active;
    }

    public function initialize(Site $site): void
    {
        $this->initialized = $site;
        $this->bootstrap($site);

        $this->events->dispatch(new SiteInitialized($site));
        do_action('kleinweb/tenancy/initialized', $site);
    }

    /**
     * Follow a `switch_blog` to the given blog.
     */
    public function switchTo(int $blogId): void
    {
        $target = $this->sites->findById($blogId);
        if ($target?->key() === null) {
            $target = null;
        }

        $previous = $this->active;
        if ($target?->id() === $previous?->id()) {
            return;
        }

        $this->revert();
        if ($target !== null) {
            $this->bootstrap($target);
        }

        $this->events->dispatch(new SiteSwitched($target, $previous));
        do_action('kleinweb/tenancy/switched', $target, $previous);
    }

    private function bootstrap(Site $site): void
    {
        foreach ($this->bootstrappers as $bootstrapper) {
            $bootstrapper->bootstrap($site);
        }

        $this->active = $site;
    }

    private function revert(): void
    {
        foreach (\array_reverse($this->bootstrappers) as $bootstrapper) {
            $bootstrapper->revert();
        }

        $this->active = null;
    }
}
