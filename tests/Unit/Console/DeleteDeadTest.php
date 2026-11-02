<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * Blogs: 1 `main`, 2 `news`.  On each, attachment 10 has its file, 11 has
 * lost it, 12 has no file recorded; post 13 is not an attachment.
 */

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Kleinweb\Lib\Console\Commands\Attachment\DeleteDead;
use Kleinweb\Lib\Tests\Support\WordPress;

use function Kleinweb\Lib\Tests\runCommand;
use function Kleinweb\Lib\Tests\tempPath;
use function Kleinweb\Lib\Tests\testApplication;

/**
 * @param array<string, mixed> $input
 *
 * @return array{int, string}
 */
function deleteDead(array $input = []): array
{
    return runCommand(new DeleteDead(), testApplication(), $input);
}

/**
 * @return list<int> attachment IDs left on a blog
 */
function attachmentsOn(int $blogId): array
{
    return array_keys(array_filter(
        WordPress::$posts[$blogId] ?? [],
        static fn (array $post): bool => $post['post_type'] === 'attachment'
            && $post['post_status'] !== 'trash',
    ));
}

beforeEach(function (): void {
    $this->uploads = tempPath('uploads');
    (new Filesystem())->ensureDirectoryExists($this->uploads);
    file_put_contents("{$this->uploads}/present.jpg", '');

    foreach ([1 => 'main', 2 => 'news'] as $blogId => $key) {
        WordPress::addSite($blogId, $key);
        $attachment = ['post_type' => 'attachment', 'post_status' => 'inherit'];
        $gone = "{$this->uploads}/gone.jpg";
        WordPress::$posts[$blogId] = [
            10 => [...$attachment, 'file' => "{$this->uploads}/present.jpg"],
            11 => [...$attachment, 'file' => $gone],
            12 => $attachment,
            13 => ['post_type' => 'post', 'post_status' => 'publish', 'file' => $gone],
        ];
    }
});

afterEach(function (): void {
    (new Filesystem())->deleteDirectory($this->uploads);
});

it('deletes attachments whose file is gone, on every site', function (): void {
    [$code, $output] = deleteDead();

    expect($code)->toBe(Command::SUCCESS)
        ->and(attachmentsOn(1))->toBe([10, 12])
        ->and(attachmentsOn(2))->toBe([10, 12])
        ->and(WordPress::$posts[1])->toHaveKey(13)
        ->and($output)->toContain(
            'Deleted attachment 11',
            'Attachment 12 has no file recorded',
            'main: 3 checked, 1 with a missing file (1 deleted, 0 trashed, 0 failed), '
            . '1 with no file recorded.',
            'news: 3 checked',
        )
    ;
});

it('says when it trashed rather than deleted', function (): void {
    WordPress::$mediaTrash = true;
    [, $output] = deleteDead(['--sites' => ['news']]);

    expect(WordPress::$posts[2][11]['post_status'])->toBe('trash')
        ->and(WordPress::$posts[1][11]['post_status'])->toBe('inherit', 'main was not selected')
        ->and($output)->toContain('Trashed attachment 11', '(0 deleted, 1 trashed, 0 failed)')
    ;
});

it('changes nothing on a dry run, and leaves the cursor for the real run', function (): void {
    [, $output] = deleteDead(['--dry-run' => true]);

    expect(attachmentsOn(1))->toBe([10, 11, 12])
        ->and($output)->toContain('Would delete attachment 11', '[DRY-RUN] main: 3 checked')
        ->and(WordPress::$options[1])->not->toHaveKey(DeleteDead::CURSOR_OPTION)
    ;

    deleteDead();
    expect(attachmentsOn(1))->toBe([10, 12]);
});

it('resumes where the last run stopped, unless asked to start again', function (): void {
    WordPress::$options[1][DeleteDead::CURSOR_OPTION] = 11;

    deleteDead(['--sites' => ['main']]);
    expect(attachmentsOn(1))->toBe([10, 11, 12], '11 was before the cursor');

    WordPress::$options[1][DeleteDead::CURSOR_OPTION] = 11;
    deleteDead(['--sites' => ['main'], '--from-scratch' => true]);
    expect(attachmentsOn(1))->toBe([10, 12]);
});

it('rewinds without checking anything', function (): void {
    WordPress::$options[2][DeleteDead::CURSOR_OPTION] = 12;

    [, $output] = deleteDead(['--rewind' => true]);

    expect(WordPress::$options[2])->not->toHaveKey(DeleteDead::CURSOR_OPTION)
        ->and(attachmentsOn(2))->toBe([10, 11, 12])
        ->and($output)->toContain('news: Rewound')
    ;
});

it('works on a single site', function (): void {
    WordPress::$multisite = false;

    [$code, $output] = deleteDead();

    expect($code)->toBe(Command::SUCCESS)
        ->and(attachmentsOn(1))->toBe([10, 12])
        ->and(attachmentsOn(2))->toBe([10, 11, 12])
        ->and($output)->toContain('3 checked')->not->toContain('main:')
    ;
});
