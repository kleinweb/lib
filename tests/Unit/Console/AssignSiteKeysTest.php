<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

/*
 * Network: 1 the main site (temple-news.test), 2 a subdomain, 3 a
 * subdirectory on the main host, 4 a demapped site with its original host
 * recorded, 5 www, 6 a second subdomain deriving the same key as 2.
 */

declare(strict_types=1);

use Illuminate\Console\Command;
use Kleinweb\Lib\Console\Commands\Tenancy\AssignSiteKeys;
use Kleinweb\Lib\Tests\Support\WordPress;

use function Kleinweb\Lib\Tests\runCommand;
use function Kleinweb\Lib\Tests\testApplication;

/**
 * @param array<string, mixed> $input
 *
 * @return array{int, string}
 */
function assignKeys(array $input = []): array
{
    return runCommand(new AssignSiteKeys(), testApplication(), $input);
}

function assignKey(int $id, string $key, bool $force = false): int
{
    return assignKeys(['--id' => (string) $id, '--key' => $key, '--force' => $force])[0];
}

function storedKey(int $id): mixed
{
    return WordPress::$meta[$id]['site_key'] ?? null;
}

beforeEach(function (): void {
    WordPress::addSite(1, domain: 'temple-news.test');
    WordPress::addSite(2, domain: 'longform.temple-news.test');
    WordPress::addSite(3, domain: 'temple-news.test', path: '/owlery/');
    WordPress::addSite(4, domain: 'thecherry.org');
    WordPress::$meta[4]['orig_host'] = 'thecherry.temple-news.test';
    WordPress::addSite(5, domain: 'www.thecherry.org');
    WordPress::addSite(6, domain: 'longform.example.org');
});

it('derives keys the way site:demap-domains does', function (): void {
    [$code, $output] = assignKeys();

    expect($code)->toBe(Command::FAILURE, 'some sites are left for --id')
        ->and(array_map(storedKey(...), [1, 2, 3, 4, 5, 6]))
        ->toBe([null, 'longform', 'owlery', 'thecherry', null, null])
        ->and($output)->toContain(
            'main site; assign with --id',
            'derived key is reserved',
            'already used by site 2',
        )
    ;
});

it('writes nothing on a dry run', function (): void {
    [, $output] = assignKeys(['--dry-run' => true]);

    expect(storedKey(2))->toBeNull()
        ->and($output)->toContain('[DRY-RUN] would assign')
    ;
});

it('keeps existing keys', function (): void {
    WordPress::$meta[2]['site_key'] = 'ttn-longform';
    assignKeys();

    expect(storedKey(2))->toBe('ttn-longform')
        ->and(storedKey(6))->toBe('longform', 'now free')
    ;
});

it('assigns one site explicitly', function (): void {
    expect(assignKey(1, 'main'))->toBe(Command::SUCCESS)
        ->and(storedKey(1))->toBe('main')
    ;
});

it('refuses duplicates, reserved keys and overwrites without --force', function (): void {
    WordPress::$meta[2]['site_key'] = 'longform';

    expect(assignKey(6, 'longform'))->toBe(Command::FAILURE)
        ->and(assignKey(5, 'www'))->toBe(Command::FAILURE)
        ->and(assignKey(2, 'other'))->toBe(Command::FAILURE)
        ->and(storedKey(2))->toBe('longform')
        ->and(assignKey(2, 'other', force: true))->toBe(Command::SUCCESS)
        ->and(storedKey(2))->toBe('other');
});
