<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Tenancy;

use Illuminate\Console\Command;
use Kleinweb\Lib\Tenancy\Exceptions\MultisiteOnly;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\SitePlugin;
use Kleinweb\Lib\Tenancy\SiteRepository;

final class ListSites extends Command
{
    protected $signature = 'site:list
        {--json : Output the list as JSON}';

    // FIXME: not domains, more like home URLs?
    protected $description = 'List sites with keys and domains';

    /**
     * @throws MultisiteOnly
     */
    public function handle(SiteRepository $sites): int
    {
        $sites->refresh();

        $keyCounts = $sites->all()
            ->map(static fn (Site $site) => $site->key()?->value)
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

        $this->table(['Site ID', 'Key', 'URL', 'Original Host', 'Site Plugin', 'Theme', 'Status'], $rows);

        return self::SUCCESS;
    }

    private static function sitePlugin(Site $site): ?string
    {
        $key = $site->key();

        return (($key !== null) && SitePlugin::exists($key)) ? SitePlugin::directory($key) : null;
    }

    /**
     * Active theme for the site.
     */
    private static function theme(Site $site): string
    {
        $stylesheet = get_blog_option($site->id(), 'stylesheet');
        $stylesheet = is_string($stylesheet) ? $stylesheet : '';
        $template = get_blog_option($site->id(), 'template');
        $template = is_string($template) ? $template : '';

        return (($template !== '') && ($template !== $stylesheet))
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
            !$key->isNamespaceable() => 'ok, but not usable as a PHP namespace',
            default => 'ok',
        };
    }
}
