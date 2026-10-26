<?php

declare(strict_types=1);

namespace Kleinweb\Sites\News\Console\Commands;

use Illuminate\Console\Command;

/**
 * Exists only in console processes for the news site:
 *
 *     wp --url=news.example.test acorn news:import-wire
 *
 * From another site's process it is not registered; run it for the news site
 * from anywhere with `wp acorn site:run news:import-wire --sites=news`.
 */
final class ImportWire extends Command
{
    /** @var string */
    protected $signature = 'news:import-wire {--desk=campus : Desk to file stories under}';

    /** @var string */
    protected $description = 'Create a draft story from the wire feed';

    public function handle(): int
    {
        $desk = $this->option('desk');
        if (!is_string($desk) || $desk === '') {
            $desk = 'campus';
        }

        // Runs for the news site, so `story` and `desk` are registered.
        $id = wp_insert_post([
            'post_type' => 'story',
            'post_status' => 'draft',
            'post_title' => 'Wire story ' . wp_date('Y-m-d H:i'),
        ], true);

        if (is_wp_error($id)) {
            $this->components->error($id->get_error_message());

            return self::FAILURE;
        }

        wp_set_object_terms($id, $desk, 'desk');
        $this->components->info("Created story {$id} in the {$desk} desk.");

        return self::SUCCESS;
    }
}
