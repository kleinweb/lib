<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Support\Collection;
use Kleinweb\Lib\Tenancy\Exceptions\MultisiteOnly;
use Kleinweb\Lib\Database\Models\Site;
use WP_Site;

/**
 * Look up sites in the current multisite network.
 *
 * Sites are loaded once per instance.  This class should be bound as a singleton in the service container.  Invoke {@see SiteRepository::refresh()} after adding or removing sites.
 */
final class SiteRepository
{
    /** @var array<int, Site>|null */
    private ?array $sites = null;

    /**
     * Every site in the multisite network keyed by its ID.
     *
     * @return Collection<int, Site>
     *
     * @throws MultisiteOnly
     */
    public function all(): Collection
    {
        return new Collection($this->load());
    }

    public function findById(int $id): ?Site
    {
        if ($this->sites !== null) {
            return $this->sites[$id] ?? null;
        }

        SiteRepository::assertMultisite();

        $site = Site::find($id);

        $inNetwork = ($site !== null)
        && ((int) $site->getCoreObject()->site_id === get_current_network_id());

        return $inNetwork ? $site : null;
    }

    /**
     * Find a site by its key.
     *
     * @throws MultisiteOnly
     */
    public function find(SiteKey|string $key): ?Site
    {
        return $this->all()->first(
            static fn (Site $site): bool => $site->key()?->equals($key) ?? false,
        );
    }

    /**
     * Sites whose keys are in the given list.
     *
     * @param list<SiteKey|string> $keys
     *
     * @return Collection<int, Site>
     *
     * @throws MultisiteOnly
     */
    public function whereKeyIn(array $keys): Collection
    {
        $keys = array_map(static fn (SiteKey|string $key): string => (string) $key, $keys);

        return $this->all()->filter(
            static fn (Site $site): bool => ($site->key() !== null)
                && in_array($site->key(), $keys, true),
        );
    }

    /**
     * The current WordPress site, respecting {@see switch_to_blog()}.
     */
    public function current(): ?Site
    {
        return $this->findById(get_current_blog_id());
    }

    public function main(): ?Site
    {
        return $this->findById(get_main_site_id());
    }

    /**
     * TODO: should this also call `load()`?
     */
    public function refresh(): void
    {
        $this->sites = null;
    }

    /**
     * @return Site[]
     *
     * @throws MultisiteOnly
     */
    private function load(): array
    {
        if ($this->sites !== null) {
            return $this->sites;
        }

        SiteRepository::assertMultisite();

        /** @var WP_Site[] $found */
        $found = get_sites([
            'network_id' => get_current_network_id(),
            'number' => 0,
            'orderby' => 'id',
            'order' => 'ASC',
            'update_site_meta_cache' => true,
        ]);

        $sites = [];
        foreach ($found as $site) {
            $sites[(int) $site->blog_id] = new Site($site);
        }

        return $this->sites = $sites;
    }

    /**
     * @throws MultisiteOnly
     */
    private static function assertMultisite(): void
    {
        if (!is_multisite()) {
            throw new MultisiteOnly();
        }
    }
}
