<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Illuminate\Console\Command;
use Kleinweb\Lib\Console\Commands\Tenancy\RunForSites;
use Kleinweb\Lib\Tests\Support\WordPress;

use function Kleinweb\Lib\Tests\runCommand;
use function Kleinweb\Lib\Tests\tempPath;
use function Kleinweb\Lib\Tests\testApplication;

beforeEach(function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(5, 'events');

    // Stands in for WP-CLI: prints its arguments, fails for site 5.
    $this->wp = tempPath('fake-wp');
    file_put_contents($this->wp, <<<'SH'
        #!/bin/sh
        echo "ran $1 $3"
        case "$1" in *site5*) exit 3 ;; esac
        SH);

    // Through sh, as the command line is run by a shell.
    $this->siteRun = fn (array $input): array => runCommand(
        new RunForSites(),
        testApplication(),
        [...$input, '--wp' => 'sh ' . $this->wp],
    );
});

afterEach(function (): void {
    unlink($this->wp);
});

it('runs each site in its own process and reports failures', function (int $parallel): void {
    [$code, $output] = ($this->siteRun)([
        'commandline' => 'cache:clear',
        '--parallel' => (string) $parallel,
    ]);

    expect($code)->toBe(Command::FAILURE)
        ->and($output)->toContain(
            'ran --url=https://site1.test/ cache:clear',
            'ran --url=https://site2.test/ cache:clear',
            'ran --url=https://site5.test/ cache:clear',
        )
        ->toMatch('#events\s*\|\s*https://site5\.test/\s*\|\s*3\s*\|#')
    ;
})->with(['one at a time' => 1, 'all at once' => 3]);

it('selects sites by key', function (): void {
    [$code, $output] = ($this->siteRun)(['commandline' => 'cache:clear', '--sites' => ['news']]);

    expect($code)->toBe(Command::SUCCESS)
        ->and($output)->toContain('site2.test')
        ->not->toContain('site1.test')
    ;
});

it('refuses a parallelism below one', function (): void {
    [$code] = ($this->siteRun)(['commandline' => 'cache:clear', '--parallel' => '0']);

    expect($code)->toBe(Command::FAILURE);
});
