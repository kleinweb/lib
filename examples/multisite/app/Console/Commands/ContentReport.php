<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Kleinweb\Lib\Console\Concerns\InteractsWithSites;
use Kleinweb\Lib\Database\Models\Site;

/**
 * A network-wide command: runs once and visits every site with
 * switch_to_blog().
 *
 *     wp acorn content:report
 *     wp acorn content:report --sites=news --sites=events
 *
 * Inside the callback, WordPress data, the per-blog cache and the visited
 * site's config overlay follow the switch, and the visited site's post types
 * are registered (forEachSite's default), so `story` counts on the news site
 * even when the command was started from the main site.
 */
final class ContentReport extends Command
{
    use InteractsWithSites;

    /** @var string */
    protected $signature = 'content:report
        {--sites=* : Only these site keys}
        {--skip-sites=* : Skip these site keys}';

    /** @var string */
    protected $description = 'Count published posts of each post type on every site';

    public function handle(): int
    {
        return $this->forEachSite(function (Site $site): void {
            $rows = [];

            foreach (get_post_types(['public' => true]) as $type) {
                $counts = wp_count_posts($type);
                $rows[] = [$type, (int) ($counts->publish ?? 0)];
            }

            $this->components->twoColumnDetail(
                "<fg=green>{$this->siteLabel($site)}</>",
                (string) config('mail.from.address'),
            );
            $this->table(['Post type', 'Published'], $rows);
        });
    }
}
