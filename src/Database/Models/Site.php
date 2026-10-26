<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Database\Models;

use Kleinweb\Lib\Tenancy\Domain;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SiteMeta;
use Webmozart\Assert\Assert;
use WP_Site;
use Closure;

final class Site extends Model
{
    public function __construct(protected WP_Site $coreObject) {}

    public static function getEntityName(): string
    {
        return 'site';
    }

    /**
     * Site ID.
     *
     * May differ across environments.  Prefer using {@see Site::key()}.
     */
    public function id(): int
    {
        return $this->coreObject->blog_id;
    }

    /**
     * Site key.
     *
     * Returns null if no site key is stored or if the stored value is invalid.
     */
    public function key(): ?SiteKey
    {
        return SiteKey::tryFrom($this->storedKey());
    }

    /**
     * Raw stored site key, valid or not.
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
     * Get a single blogmeta value for the site.
     */
    public function meta(SiteMeta|string $key): mixed
    {
        $key = ($key instanceof SiteKey) ? $key->value : $key;

        return get_site_meta($this->id(), $key, true);
    }

    /**
     * Invoke a callback in this site's context, then restore the previous site.
     *
     * Uses {@see switch_to_blog()} under the hood.
     *
     * Database tables, options, and object cache follow the current site context.  Providers, routes, views, and post types remain tied to the initially-loaded site.
     *
     * The initial site will be restored even if the callback throws or leaves extra `switch_to_blog()` invocations unrestored.
     *
     * @template TReturn
     *
     * @param Closure(self): TReturn $callback
     *
     * @return TReturn
     */
    public function run(Closure $callback): mixed
    {
        $depth = self::switchDepth();

        switch_to_blog($this->id());

        try {
            return $callback($this);
        } finally {
            while (self::switchDepth() > $depth && restore_current_blog()) {
                // Unwind to the initial depth of this invocation.
            }
        }
    }

    /**
     * Number of unrestored {@see switch_to_blog()} invocations.
     */
    private static function switchDepth(): int
    {
        $stack = $GLOBALS['_wp_switched_stack'] ?? [];

        return is_array($stack) ? count($stack) : 0;
    }

    public function getCoreObject(): WP_Site
    {
        return $this->coreObject;
    }

    /**
     * Find a site by ID or core object.
     *
     * To find a site by key, use {@see \Kleinweb\Lib\Tenancy\SiteRepository::find()}.
     *
     * @param int|WP_Site $identifier
     */
    public static function find($identifier): ?static
    {
        $site = is_int($identifier) ? get_site($identifier) : $identifier;

        return ($identifier instanceof WP_Site) ? new static($site) : null;
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
