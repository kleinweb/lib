<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Database\Models;

use Closure;
use Kleinweb\Lib\Tenancy\Domain;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SiteMeta;
use Webmozart\Assert\Assert;
use WP_Site;

use function get_blog_option;
use function get_home_url;
use function get_site;
use function get_site_meta;
use function is_int;
use function is_main_site;
use function is_string;
use function restore_current_blog;
use function switch_to_blog;

/**
 * A site in a WordPress multisite network.
 *
 * Wraps {@see WP_Site} and its blogmeta.  Meta is read on each call, so
 * values written elsewhere in the request (for example by
 * `site:assign-keys`) are never stale.
 *
 * @phpstan-consistent-constructor
 */
final class Site extends Model
{
    public function __construct(protected WP_Site $coreObject) {}

    public static function getEntityName(): string
    {
        return 'site';
    }

    /**
     * Blog ID.  Differs between environments; prefer {@see Site::key()}.
     */
    public function id(): int
    {
        return (int) $this->coreObject->blog_id;
    }

    /**
     * The site's key, or null if none is stored or the stored value is invalid.
     */
    public function key(): ?SiteKey
    {
        return SiteKey::tryFrom($this->storedKey());
    }

    /**
     * The raw stored key, valid or not.
     */
    public function storedKey(): ?string
    {
        $value = $this->meta(SiteMeta::Key);

        return (is_string($value) && $value !== '') ? $value : null;
    }

    /**
     * Current host and path from `wp_blogs`.
     */
    public function domain(): Domain
    {
        return Domain::fromSite($this->coreObject);
    }

    /**
     * Host and path from before the site was demapped, if recorded.
     */
    public function originalDomain(): ?Domain
    {
        $host = $this->meta(SiteMeta::OriginalHost);
        if (!is_string($host) || $host === '') {
            return null;
        }

        $path = $this->meta(SiteMeta::OriginalPath);

        return new Domain($host, is_string($path) ? $path : '/');
    }

    public function name(): string
    {
        $name = get_blog_option($this->id(), 'blogname');

        return is_string($name) ? $name : '';
    }

    public function url(string $path = ''): string
    {
        return get_home_url($this->id(), $path);
    }

    public function isMain(): bool
    {
        return is_main_site($this->id());
    }

    /**
     * Single blogmeta value for the site.
     */
    public function meta(SiteMeta|string $key): mixed
    {
        $key = ($key instanceof SiteMeta) ? $key->value : $key;

        return get_site_meta($this->id(), $key, true);
    }

    /**
     * Run a callback in this site's context, then restore the previous site.
     *
     * Switches WordPress data (database tables, options, Acorn's cache and
     * session prefixes); it does not load this site's service providers.
     *
     * @template TReturn
     *
     * @param Closure(self): TReturn $callback
     *
     * @return TReturn
     */
    public function run(Closure $callback): mixed
    {
        switch_to_blog($this->id());

        try {
            return $callback($this);
        } finally {
            restore_current_blog();
        }
    }

    public function getCoreObject(): WP_Site
    {
        return $this->coreObject;
    }

    /**
     * Find a site by blog ID or core object.
     *
     * To find a site by key, use {@see \Kleinweb\Lib\Tenancy\SiteRepository::find()}.
     *
     * @param int|WP_Site $site
     */
    public static function find($site): ?static
    {
        $site = is_int($site) ? get_site($site) : $site;

        return ($site instanceof WP_Site) ? new static($site) : null;
    }

    /**
     * @param int|WP_Site $existing
     */
    public static function newFromExisting($existing): static
    {
        $site = ($existing instanceof WP_Site) ? $existing : get_site($existing);
        Assert::isInstanceOf($site, WP_Site::class);

        return new static($site);
    }
}
