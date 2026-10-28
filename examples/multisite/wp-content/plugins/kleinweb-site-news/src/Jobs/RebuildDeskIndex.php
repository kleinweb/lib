<?php

declare(strict_types=1);

namespace Kleinweb\Sites\News\Jobs;

use App\Contracts\Masthead;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Kleinweb\Lib\Tenancy\Queue\RunsOnSiteWorker;
use Kleinweb\Lib\Tenancy\Queue\ShouldRunOnSiteWorker;

/**
 * Needs the news site's bindings (NewsMasthead), so it runs on the news
 * site's own worker:
 *
 *     wp --url=news.example.test acorn queue:work --queue=site-news
 *
 * Dispatched from the news site (or while switched to it), it goes on the
 * `site-news` queue.  A worker started for another site fails it rather
 * than running it with that site's bindings.
 */
final class RebuildDeskIndex implements ShouldRunOnSiteWorker
{
    use Dispatchable;
    use Queueable;
    use RunsOnSiteWorker;

    public function handle(Masthead $masthead): void
    {
        $terms = get_terms(['taxonomy' => 'desk', 'hide_empty' => false, 'fields' => 'slugs']);

        set_transient('news_desk_index', [
            'title' => $masthead->title(),
            'desks' => is_array($terms) ? $terms : [],
        ]);
    }
}
