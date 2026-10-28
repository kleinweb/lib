<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SiteRepository;

use function get_blog_option;
use function is_string;
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
                'site_plugin' => self::sitePlugin($site),
                'theme' => self::theme($site),
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

        $this->table(
            ['Blog ID', 'Key', 'URL', 'Original host', 'Site plugin', 'Theme', 'Status'],
            $rows,
        );

        return self::SUCCESS;
    }

    /**
     * The site plugin's directory, if the site has one.
     */
    private static function sitePlugin(Site $site): ?string
    {
        $key = $site->key();

        return ($key !== null && SitePlugin::exists($key)) ? SitePlugin::directory($key) : null;
    }

    /**
     * The active theme, as `child (parent)` for a child theme.
     */
    private static function theme(Site $site): string
    {
        $stylesheet = get_blog_option($site->id(), 'stylesheet');
        $template = get_blog_option($site->id(), 'template');
        $stylesheet = is_string($stylesheet) ? $stylesheet : '';
        $template = is_string($template) ? $template : '';

        return ($template !== '' && $template !== $stylesheet)
            ? "{$stylesheet} ({$template})"
            : $stylesheet;
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
