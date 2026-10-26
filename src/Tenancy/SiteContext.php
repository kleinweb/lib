<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Contracts\BlogBootstrapper;
use Kleinweb\Lib\Tenancy\Contracts\SiteBootstrapper;
use Kleinweb\Lib\Tenancy\Events\SiteInitialized;
use Kleinweb\Lib\Tenancy\Events\SiteSwitched;
use Throwable;

use function array_reverse;
use function do_action;

/**
 * Keeps the application in step with WordPress's current blog.
 *
 * Two things are tracked, mirroring Core's own split between code and data:
 *
 * - The *initialized* site: the blog WordPress loaded for this request or
 *   process.  Its providers, routes, views and commands are registered once
 *   and never change, just as `switch_to_blog()` never reloads plugins or the
 *   theme.
 * - The *active* blog: follows every `switch_to_blog()` and
 *   `restore_current_blog()`, including Core's internal switches.  Blog
 *   bootstrappers (cache isolation) run for every blog; site bootstrappers
 *   (the config overlay) run for blogs with a site key.
 *
 * Nothing here may break a switch: Core has already changed its globals when
 * `switch_blog` fires, so an exception escaping this class would leave the
 * caller switched with no restore.  Failures are reported and skipped.
 */
final class SiteContext
{
    private ?Site $initialized = null;

    private ?Site $active = null;

    private ?int $activeBlogId = null;

    /**
     * @param list<BlogBootstrapper>   $blogBootstrappers
     * @param list<SiteBootstrapper>   $siteBootstrappers
     * @param Closure(Throwable): void $report
     */
    public function __construct(
        private readonly SiteRepository $sites,
        private readonly Dispatcher $events,
        private readonly array $blogBootstrappers,
        private readonly array $siteBootstrappers,
        private readonly Closure $report,
    ) {}

    /**
     * The site whose providers were registered for this request, if keyed.
     */
    public function initialized(): ?Site
    {
        return $this->initialized;
    }

    /**
     * The keyed site whose config is currently applied, if any.
     */
    public function active(): ?Site
    {
        return $this->active;
    }

    /**
     * Whether a site's providers, routes, views and commands are loaded.
     *
     * Only true for the site WordPress loaded.  Code that needs another
     * site's bindings must run in a process for that site, for example
     * through `site:run`.
     */
    public function hasLoaded(Site $site): bool
    {
        return $this->initialized !== null && $this->initialized->id() === $site->id();
    }

    /**
     * Record the request site, then bring state in line with the current blog.
     *
     * The current blog may differ from the request site when initialization
     * happens inside a `switch_to_blog()`.
     */
    public function initialize(?Site $site, int $currentBlogId): void
    {
        $this->initialized = $site;
        $this->sync($currentBlogId);
    }

    /**
     * Announce initialization once every provider has booted, so listeners
     * registered in `boot()` receive it.
     */
    public function announce(): void
    {
        if ($this->initialized === null) {
            return;
        }

        $site = $this->initialized;
        $this->guard(function () use ($site): void {
            $this->events->dispatch(new SiteInitialized($site));
            do_action('kleinweb/tenancy/initialized', $site);
        });
    }

    /**
     * Follow a `switch_blog` to the given blog.  Safe to call repeatedly.
     */
    public function sync(int $blogId): void
    {
        if ($blogId !== $this->activeBlogId) {
            $this->activeBlogId = $blogId;
            $this->switchSite($blogId);
        }

        // After the config overlay, in case a site overlays cache settings.
        foreach ($this->blogBootstrappers as $bootstrapper) {
            $this->guard(static fn () => $bootstrapper->apply($blogId));
        }
    }

    private function switchSite(int $blogId): void
    {
        $target = $this->keyedSite($blogId);
        $previous = $this->active;
        if ($target?->id() === $previous?->id()) {
            return;
        }

        foreach (array_reverse($this->siteBootstrappers) as $bootstrapper) {
            $this->guard(static fn () => $bootstrapper->revert());
        }

        $this->active = $target;

        if ($target !== null) {
            foreach ($this->siteBootstrappers as $bootstrapper) {
                $this->guard(static fn () => $bootstrapper->bootstrap($target));
            }
        }

        $this->guard(function () use ($target, $previous): void {
            $this->events->dispatch(new SiteSwitched($target, $previous));
            do_action('kleinweb/tenancy/switched', $target, $previous);
        });
    }

    /**
     * The site for a blog if it has a key; null for keyless or unknown blogs.
     */
    private function keyedSite(int $blogId): ?Site
    {
        try {
            $site = $this->sites->findById($blogId);
        } catch (Throwable $e) {
            ($this->report)($e);

            return null;
        }

        return ($site?->key() !== null) ? $site : null;
    }

    /**
     * @param Closure(): mixed $callback
     */
    private function guard(Closure $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            ($this->report)($e);
        }
    }
}
