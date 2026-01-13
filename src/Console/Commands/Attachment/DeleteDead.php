<?php

// SPDX-FileCopyrightText: (C) 2025-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Console\Commands\Attachment;

use Alley\WP_Bulk_Task\Bulk_Task;
use Alley\WP_Bulk_Task\Bulk_Task_Side_Effects;
use Alley\WP_Bulk_Task\Progress\PHP_CLI_Progress_Bar as ProgressBar;
use Illuminate\Console\Command;

/**
 * Delete attachments whose file is missing from the filesystem.
 */
final class DeleteDead extends Command
{
    use Bulk_Task_Side_Effects;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
    protected $signature = 'app:attachment:delete-dead {--dry-run} {--from-scratch} {--rewind}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete attachments whose file is missing from the filesystem';

    /**
     * Delete dead attachments.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Run the command without making any changes.
     *
     * [--from-scratch]
     * : Run the bulk task from scratch.
     *
     * [--rewind]
     * : Resets the cursor so the next time the command is run it will start from the beginning.
     */
    public function handle(): void
    {
        $dryRun = $this->option('dry-run');
        $fromScratch = $this->option('from-scratch');
        $rewind = $this->option('rewind');

        $dryRunPrefix = $dryRun ? '[DRY-RUN]' : '';

        $bulkTask = new Bulk_Task(
            'attachment-delete-dead',
            $dryRun ? null
                : new ProgressBar(
                    'Bulk Task: attachment_delete_dead',
                ),
        );

        if ($rewind) {
            $bulkTask->cursor->reset();
            // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
            $this->alert('Rewound the cursor. Run again without the --rewind flag to process posts.');

            return;
        }

        if ($fromScratch) {
            $bulkTask->cursor->reset();
        }

        $this->pause_side_effects();

        $bulkTask->run(
            [
                'post_status' => 'any',
                'post_type' => 'attachment',
            ],
            function ($it) use ($dryRun, $dryRunPrefix) {
                $file = get_attached_file($it->ID);

                if (!$file) {
                    // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong -- Log message string.
                    $this->warn("Attachment {$it->ID} does not have a corresponding file! Skipping...");

                    return;
                }

                if (file_exists($file)) {
                    return;
                }

                $this->line("{$dryRunPrefix} Deleting dead attachment {$it->ID}");

                if (! $dryRun) {
                    wp_delete_post($it->ID, force_delete: false);
                }
            },
        );

        $this->resume_side_effects();

        $this->info("{$dryRunPrefix} Done.");
    }
}
