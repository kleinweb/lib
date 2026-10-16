<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Support\Collection;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\MultisiteOnly;
use WP_Site;

use function array_map;
use function get_current_blog_id;
use function get_current_network_id;
use function get_main_site_id;
use function get_sites;
use function is_multisite;

/**
 * Looks up sites in the current network.
 *
 * Sites are loaded once per instance; bind it as a singleton and call
 * {@see SiteRepository::refresh()} after adding or removing sites.
 */
final class SiteRepository
{
    /** @var array<int, Site>|null */
    private ?array $sites = null;

    /**
     * Every site in the current network, keyed by blog ID.
     *
     * @return Collection<int, Site>
     *
     * @throws MultisiteOnly
     */
    public function all(): Collection
    {
        return new Collection($this->load());
    }

    /**
     * @throws MultisiteOnly
     */
    public function findById(int $id): ?Site
    {
        return $this->load()[$id] ?? null;
    }

    /**
     * Find a site by key.  Returns the lowest blog ID if a key is duplicated.
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
            static fn (Site $site): bool => $site->key() !== null
                && \in_array($site->key()->value, $keys, true),
        );
    }

    /**
     * The site WordPress is currently on, including inside `switch_to_blog()`.
     *
     * @throws MultisiteOnly
     */
    public function current(): ?Site
    {
        return $this->findById(get_current_blog_id());
    }

    /**
     * @throws MultisiteOnly
     */
    public function main(): ?Site
    {
        return $this->findById(get_main_site_id());
    }

    public function refresh(): void
    {
        $this->sites = null;
    }

    /**
     * @return array<int, Site>
     *
     * @throws MultisiteOnly
     */
    private function load(): array
    {
        if ($this->sites !== null) {
            return $this->sites;
        }

        if (!is_multisite()) {
            throw new MultisiteOnly();
        }

        // `get_sites()` returns 100 sites unless `number` is set; 0 means no limit.
        /** @var list<WP_Site> $found */
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
}
