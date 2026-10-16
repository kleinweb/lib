<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\SiteRepository;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class ListSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site:list {--json : Output the list as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the sites in the network with their keys and domains';

    public function handle(SiteRepository $sites): int
    {
        $sites->refresh();

        $keyCounts = $sites->all()
            ->map(static fn (Site $site): ?string => $site->key()?->value)
            ->filter()
            ->countBy()
        ;

        $rows = $sites->all()
            ->map(static fn (Site $site): array => [
                'blog_id' => $site->id(),
                'key' => $site->storedKey(),
                'url' => $site->url(),
                'original_host' => $site->originalDomain()?->host,
                'status' => self::status($site, $keyCounts->all()),
            ])
            ->values()
            ->all()
        ;

        if ($this->option('json')) {
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
            $this->line(json_encode($rows, $flags));

            return self::SUCCESS;
        }

        $this->table(['Blog ID', 'Key', 'URL', 'Original host', 'Status'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param array<string, int> $keyCounts
     */
    private static function status(Site $site, array $keyCounts): string
    {
        $stored = $site->storedKey();
        $key = $site->key();

        return match (true) {
            $stored === null => 'no key',
            $key === null => 'invalid key',
            ($keyCounts[$key->value] ?? 0) > 1 => 'duplicate key',
            $key->isReserved() => 'reserved key',
            !$key->isNamespaceable() => 'ok (not usable as a PHP namespace)',
            default => 'ok',
        };
    }
}
