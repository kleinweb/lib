<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use Kleinweb\Lib\Tenancy\SiteKey;
use Kleinweb\Lib\Tenancy\SiteMeta;
use Kleinweb\Lib\Tenancy\SiteRepository;

final class AssignSiteKeys extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'site:assign-keys
        {--dry-run : Report what would be assigned without writing anything}
        {--id= : Assign a key to a single site by ID}
        {--key= : The key to assign to a site, with --id}
        {--force : With --id, replace an existing site key}
    ';

    /**
     * The console command description.
     */
    protected $description = 'Store a site key in blogmeta';

    private const array COLUMNS = ['ID', 'URL', 'Key', 'Result'];

    public function handle(SiteRepository $sites): int
    {
        if (!is_site_meta_supported()) {
            $this->error(
                'This multisite instance has no blogmeta table, so site keys cannot be stored.'
            . 'Run `wp core update-db --network` first.',
            );

            return self::FAILURE;
        }

        $sites->refresh();

        return $this->option('id') !== null
            ? $this->assignOne($sites)
            : $this->assignAll($sites);
    }

    /*
     * Derive and store keys for every site without one.
     *
     * Sites with an existing key will be left alone.  Exits with error when any site is left
     * without a valid key, so any remaining sites can be assigned a key with `--id`.
     */
    private function assignAll(SiteRepository $sites): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mainHost = $sites->main()?->domain()->host;

        /** @var array<string, int> $taken key => site ID */
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
                    $site->key() === null => 'invalid stored key --- fix with `--id` and `--force`',
                    $owner !== $site->id() => sprintf(
                        'duplicate of site %d --- fix with `--id` and `--force`',
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
                $rows[] = $this->row($site, null, $site->isMain() ? 'main site --- assign with `--id`'
                : 'could not derive a site key --- assign with `--id`');
                $unresolved++;

                continue;
            }

            if ($candidate->isReserved()) {
                $rows[] = $this->row(
                    $site,
                    $candidate->value,
                    'derived site key is reserved --- assign with `--id`',
                );
                $unresolved++;

                continue;
            }

            if (isset($taken[$candidate->value])) {
                $rows[] = $this->row($site, $candidate->value, sprintf(
                    'already used by site %d --- assign with `--id`',
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

        $this->info($this->withDryRunLabel('Every site already has a corresponding key.'));

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
            $this->error('--id must be a site ID.');

            return self::FAILURE;
        }

        $value = $this->option('key');
        if (!is_string($value) || ($value === '')) {
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
            $this->error(sprintf('Could not find a site with id "%s"', $id));

            return self::FAILURE;
        }

        $owner = $sites->find($key);
        if (($owner !== null) && ($owner->id() !== $site->id())) {
            $this->error(sprintf('Site key "%s" is owned by site %s.', $key->value, $this->formatSiteLabel($owner)));

            return self::FAILURE;
        }

        $stored = $site->storedKey();
        if ($stored === $key->value) {
            $this->info(sprintf('Site %s already has key "%s".', $this->formatSiteLabel($site), $key->value));

            return self::SUCCESS;
        }

        if ($stored !== null) {
            $this->error(sprintf('Site %s already has key "%s".  Re-run with --force to update anyway.', $this->formatSiteLabel($site), $stored));

            return self::FAILURE;
        }

        if (!$key->isNamespaceable()) {
            $this->error(sprintf('"%s" cannot be used as a site key because it cannot be converted into a valid PHP namespace.', $key->value));

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info($this->withDryRunLabel(sprintf(
                'Would set key "%s" on site %s',
                $key->value,
                $this->formatSiteLabel($site),
            )));

            return self::SUCCESS;
        }

        if (!$this->store($site, $key, replace: $stored !== null)) {
            $this->error(sprintf('Could not write key "%s" for site %s.', $key->value, $this->formatSiteLabel($site)));

            return self::FAILURE;
        }

        $this->info(sprintf('Mapped site %s to site key "%s".', $this->formatSiteLabel($site), $site->id()));

        return self::SUCCESS;
    }

    /**
     * Derive a site key using the same leftmost-label rule as {@see DemapDomains}.
     */
    private static function derive(Site $site, ?string $mainHost): ?SiteKey
    {
        $orig = $site->originalDomain();
        if ($orig !== null) {
            return SiteKey::fromHost($orig->host);
        }

        if ($site->isMain()) {
            return null;
        }

        $domain = $site->domain();

        // FIXME: use existing util fn
        return ($domain->host === $mainHost)
            ? SiteKey::fromPath($domain->path)
            : SiteKey::fromHost($domain->host);
    }

    private function store(Site $site, SiteKey $key, bool $replace): bool
    {
        $result = $replace
            ? update_site_meta($site->id(), SiteMeta::Key->value, $key->value)
            : add_site_meta($site->id(), SiteMeta::Key->value, $key->value, unique: true);

        return $result !== false;
    }

    /**
     * @return array{int, string, string, string}
     */
    private function row(Site $site, ?string $key, string $result): array
    {
        return [$site->id(), $site->url(), $key ?? '', $this->withDryRunLabel($result)];
    }

    private function withDryRunLabel(string $text): string
    {
        return $this->option('dry-run') ? sprintf('[DRY-RUN] %s', $text) : $text;
    }

    private function formatSiteLabel(Site $site): string
    {
        return sprintf('%d (%s)', $site->id(), $site->name());
    }
}
