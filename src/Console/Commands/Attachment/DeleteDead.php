<?php

// SPDX-FileCopyrightText: (C) 2025-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Attachment;

use Illuminate\Console\Command;
use Kleinweb\Lib\Console\Concerns\InteractsWithSites;
use Kleinweb\Lib\Database\Batch\MemoryCursor;
use Kleinweb\Lib\Database\Batch\OptionCursor;
use Kleinweb\Lib\Database\Batch\PostBatches;
use Kleinweb\Lib\Database\Models\Site;
use WP_Post;

use function file_exists;
use function get_attached_file;
use function get_post_status;
use function is_multisite;
use function is_string;
use function sprintf;
use function wp_delete_post;

/**
 * Delete attachments whose file is missing from the filesystem.
 *
 * Resumable: where each site's run got to is kept in an option, so an
 * interrupted run carries on from there.  On multisite, every site is
 * checked unless `--sites` or `--skip-sites` narrows it.
 */
final class DeleteDead extends Command
{
    use InteractsWithSites;

    /**
     * Where each site's run got to.  The name is the one Alley's
     * wp-bulk-task used, so a run interrupted before the switch resumes.
     */
    public const string CURSOR_OPTION = 'bt_attachment-delete-dead';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:attachment:delete-dead
        {--dry-run : Report what would be deleted without deleting it}
        {--from-scratch : Start from the first attachment instead of resuming}
        {--rewind : Only reset where the next run starts}
        {--sites=* : Only these site keys (multisite)}
        {--skip-sites=* : Skip these site keys (multisite)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete attachments whose file is missing from the filesystem';

    /**
     * @var array{checked: int, missing: int, unrecorded: int, deleted: int, trashed: int, failed: int}
     */
    private array $counts;

    public function handle(): int
    {
        if (!is_multisite()) {
            return $this->checkSite();
        }

        return $this->forEachSite(
            fn (Site $site): int => $this->checkSite($site),
            contentTypes: false,
        );
    }

    /**
     * Check the current blog's attachments.
     */
    private function checkSite(?Site $site = null): int
    {
        $label = ($site !== null) ? "{$this->siteLabel($site)}: " : '';
        $stored = new OptionCursor(self::CURSOR_OPTION);

        if ($this->option('rewind')) {
            $stored->reset();
            $this->components->info(
                "{$label}Rewound. The next run starts from the first attachment.",
            );

            return self::SUCCESS;
        }

        if ($this->option('from-scratch')) {
            $stored->reset();
        }

        $dryRun = (bool) $this->option('dry-run');

        // A dry run reads from the stored cursor but never moves it, so the
        // real run after it still checks everything.
        $cursor = $dryRun ? new MemoryCursor($stored->get()) : $stored;

        $this->counts = [
            'checked' => 0,
            'missing' => 0,
            'unrecorded' => 0,
            'deleted' => 0,
            'trashed' => 0,
            'failed' => 0,
        ];

        PostBatches::query(['post_type' => 'attachment', 'post_status' => 'any'])
            ->resumeFrom($cursor)
            ->lazy()
            ->each(fn (WP_Post $attachment) => $this->check($attachment, $dryRun))
        ;

        $this->summarize($label, $dryRun);

        return ($this->counts['failed'] === 0) ? self::SUCCESS : self::FAILURE;
    }

    private function check(WP_Post $attachment, bool $dryRun): void
    {
        $this->counts['checked']++;
        $file = get_attached_file($attachment->ID);

        if (!is_string($file) || $file === '') {
            $this->counts['unrecorded']++;
            $this->components->warn("Attachment {$attachment->ID} has no file recorded; skipped.");

            return;
        }

        if (file_exists($file)) {
            return;
        }

        $this->counts['missing']++;

        if ($dryRun) {
            $this->line("Would delete attachment {$attachment->ID} ({$file})");

            return;
        }

        // Moves it to the trash when MEDIA_TRASH is on, deletes it otherwise.
        if (!wp_delete_post($attachment->ID, force_delete: false)) {
            $this->counts['failed']++;
            $this->components->error("Could not delete attachment {$attachment->ID}.");

            return;
        }

        $trashed = get_post_status($attachment->ID) === 'trash';
        $this->counts[$trashed ? 'trashed' : 'deleted']++;
        $this->line(sprintf(
            '%s attachment %d (%s)',
            $trashed ? 'Trashed' : 'Deleted',
            $attachment->ID,
            $file,
        ));
    }

    private function summarize(string $label, bool $dryRun): void
    {
        $counts = $this->counts;

        $this->components->info(sprintf(
            '%s%s%d checked, %d with a missing file%s, %d with no file recorded.',
            $dryRun ? '[DRY-RUN] ' : '',
            $label,
            $counts['checked'],
            $counts['missing'],
            $dryRun ? '' : sprintf(
                ' (%d deleted, %d trashed, %d failed)',
                $counts['deleted'],
                $counts['trashed'],
                $counts['failed'],
            ),
            $counts['unrecorded'],
        ));
    }
}
