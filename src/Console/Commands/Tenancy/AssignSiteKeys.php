<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SiteMeta;
use Kleinweb\Lib\Tenancy\SiteRepository;

use function add_site_meta;
use function filter_var;
use function is_string;
use function sprintf;
use function update_site_meta;

use const FILTER_VALIDATE_INT;

final class AssignSiteKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site:assign-keys
        {--dry-run : Report what would be assigned without writing anything}
        {--id= : Assign a key to a single site, by blog ID}
        {--key= : The key to assign with --id}
        {--force : With --id, replace a key the site already has}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store a site key in blogmeta for sites that do not have one';

    private const array COLUMNS = ['Blog ID', 'URL', 'Key', 'Result'];

    public function handle(SiteRepository $sites): int
    {
        $sites->refresh();

        return $this->option('id') !== null
            ? $this->assignOne($sites)
            : $this->assignAll($sites);
    }

    /**
     * Derive and store keys for every site without one.
     *
     * Sites that already have a key are left alone.  Returns failure when any
     * site is left without a valid key, so the remaining ones can be assigned
     * with `--id`.
     */
    private function assignAll(SiteRepository $sites): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mainHost = $sites->main()?->domain()->host;

        /** @var array<string, int> $taken key => blog ID */
        $taken = [];
        foreach ($sites->all() as $site) {
            $key = $site->key();
            if ($key !== null && !isset($taken[$key->value])) {
                $taken[$key->value] = $site->id();
            }
        }

        $rows = [];
        $unresolved = 0;

        foreach ($sites->all() as $site) {
            $stored = $site->storedKey();

            if ($stored !== null) {
                $owner = $taken[$stored] ?? null;
                $result = match (true) {
                    $site->key() === null => 'invalid stored key; fix with --id and --force',
                    $owner !== $site->id() => sprintf(
                        'duplicate of site %d; fix with --id and --force',
                        $owner,
                    ),
                    default => 'kept',
                };
                $rows[] = $this->row($site, $stored, $result);
                $unresolved += ($result === 'kept') ? 0 : 1;

                continue;
            }

            $candidate = self::derive($site, $mainHost);

            if ($candidate === null) {
                $rows[] = $this->row($site, null, $site->isMain()
                    ? 'main site; assign with --id'
                    : 'could not derive a key; assign with --id');
                $unresolved++;

                continue;
            }

            if ($candidate->isReserved()) {
                $rows[] = $this->row(
                    $site,
                    $candidate->value,
                    'derived key is reserved; assign with --id',
                );
                $unresolved++;

                continue;
            }

            if (isset($taken[$candidate->value])) {
                $rows[] = $this->row($site, $candidate->value, sprintf(
                    'already used by site %d; assign with --id',
                    $taken[$candidate->value],
                ));
                $unresolved++;

                continue;
            }

            if (!$dryRun && !$this->store($site, $candidate, replace: false)) {
                $rows[] = $this->row($site, $candidate->value, 'write failed');
                $unresolved++;

                continue;
            }

            $taken[$candidate->value] = $site->id();
            $result = $dryRun ? 'would assign' : 'assigned';
            if (!$candidate->isNamespaceable()) {
                $result .= ' (not usable as a PHP namespace)';
            }
            $rows[] = $this->row($site, $candidate->value, $result);
        }

        $this->table(self::COLUMNS, $rows);

        if ($unresolved > 0) {
            $this->warn(sprintf('%d site(s) still need a key.', $unresolved));

            return self::FAILURE;
        }

        $this->info($dryRun ? '[DRY-RUN] No changes written.' : 'Every site has a key.');

        return self::SUCCESS;
    }

    /**
     * Assign an explicit key to one site.
     */
    private function assignOne(SiteRepository $sites): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $id = filter_var($this->option('id'), FILTER_VALIDATE_INT);
        if ($id === false) {
            $this->error('--id must be a blog ID.');

            return self::FAILURE;
        }

        $value = $this->option('key');
        if (!is_string($value) || $value === '') {
            $this->error('--key is required with --id.');

            return self::FAILURE;
        }

        try {
            $key = SiteKey::from($value);
        } catch (InvalidSiteKey $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($key->isReserved()) {
            $this->error(sprintf('"%s" is reserved and cannot be a site key.', $key->value));

            return self::FAILURE;
        }

        $site = $sites->findById($id);
        if ($site === null) {
            $this->error(sprintf('No site with blog ID %d in this network.', $id));

            return self::FAILURE;
        }

        $owner = $sites->find($key);
        if ($owner !== null && $owner->id() !== $site->id()) {
            $this->error(sprintf('"%s" is already used by site %d.', $key->value, $owner->id()));

            return self::FAILURE;
        }

        $stored = $site->storedKey();
        if ($stored === $key->value) {
            $this->info(sprintf('Site %d already has key "%s".', $site->id(), $key->value));

            return self::SUCCESS;
        }

        if ($stored !== null && !$force) {
            $this->error(sprintf(
                'Site %d already has key "%s"; use --force to replace it.',
                $site->id(),
                $stored,
            ));

            return self::FAILURE;
        }

        if (!$key->isNamespaceable()) {
            $this->warn(sprintf(
                '"%s" starts with a digit; it cannot be used for site-specific PHP code.',
                $key->value,
            ));
        }

        if ($dryRun) {
            $this->info(sprintf(
                '[DRY-RUN] Would set key "%s" on site %d.',
                $key->value,
                $site->id(),
            ));

            return self::SUCCESS;
        }

        if (!$this->store($site, $key, replace: $stored !== null)) {
            $this->error(sprintf('Could not write key for site %d.', $site->id()));

            return self::FAILURE;
        }

        $this->info(sprintf('Set key "%s" on site %d.', $key->value, $site->id()));

        return self::SUCCESS;
    }

    /**
     * Derive a key using the same leftmost-label rule as `site:demap-domains`.
     *
     * 1. The original host recorded when the site was demapped.
     * 2. For a subdirectory site on the main host, the first path segment.
     * 3. Otherwise the site's current host.
     */
    private static function derive(Site $site, ?string $mainHost): ?SiteKey
    {
        $original = $site->originalDomain();
        if ($original !== null) {
            return SiteKey::fromHost($original->host);
        }

        if ($site->isMain()) {
            return null;
        }

        $domain = $site->domain();

        return ($domain->host === $mainHost)
            ? SiteKey::fromPath($domain->path)
            : SiteKey::fromHost($domain->host);
    }

    private function store(Site $site, SiteKey $key, bool $replace): bool
    {
        $result = $replace
            ? update_site_meta($site->id(), SiteMeta::Key->value, $key->value)
            : add_site_meta($site->id(), SiteMeta::Key->value, $key->value, true);

        return $result !== false;
    }

    /**
     * @return array{int, string, string, string}
     */
    private function row(Site $site, ?string $key, string $result): array
    {
        $prefix = $this->option('dry-run') ? '[DRY-RUN] ' : '';

        return [$site->id(), $site->url(), $key ?? '', $prefix . $result];
    }
}
