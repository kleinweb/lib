<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Kleinweb\Lib\Console\Commands\Tenancy\ListSites;
use Kleinweb\Lib\Tests\Support\WordPress;

use function Kleinweb\Lib\Tests\runCommand;
use function Kleinweb\Lib\Tests\testApplication;

it('lists keys, site plugins and themes', function (): void {
    WordPress::addSite(1, 'main');
    WordPress::addSite(2, 'news');
    WordPress::addSite(3);
    WordPress::addSite(4, 'news');
    WordPress::$options[1] += ['stylesheet' => 'kleinweb-base', 'template' => 'kleinweb-base'];
    WordPress::$options[2] += ['stylesheet' => 'kleinweb-news', 'template' => 'kleinweb-base'];

    [, $output] = runCommand(new ListSites(), testApplication(), ['--json' => true]);
    $rows = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect(array_column($rows, 'site_plugin'))
        ->toBe([null, 'kleinweb-site-news', null, 'kleinweb-site-news'])
        ->and(array_column($rows, 'theme'))
        ->toBe(['kleinweb-base', 'kleinweb-news (kleinweb-base)', '', ''])
        ->and(array_column($rows, 'status'))
        ->toBe(['ok', 'duplicate key', 'no key', 'duplicate key'])
    ;
});
