<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Console;

use Illuminate\Foundation\Application;
use Kleinweb\Lib\Console\Commands\Tenancy\ListSites;
use Kleinweb\Lib\Tenancy\SiteRepository;
use Kleinweb\Lib\Tests\Support\TestCase;
use Kleinweb\Lib\Tests\Support\WordPress;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_column;
use function json_decode;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;

final class ListSitesTest extends TestCase
{
    public function testListsKeysSitePluginsAndThemes(): void
    {
        WordPress::addSite(1, 'main');
        WordPress::addSite(2, 'news');
        WordPress::addSite(3);
        WordPress::addSite(4, 'news');
        WordPress::$options[2] += ['stylesheet' => 'kleinweb-news', 'template' => 'kleinweb-base'];
        WordPress::$options[1] += ['stylesheet' => 'kleinweb-base', 'template' => 'kleinweb-base'];

        $app = new Application(sys_get_temp_dir());
        $app->instance(SiteRepository::class, new SiteRepository());
        $command = new ListSites();
        $command->setLaravel($app);
        $output = new BufferedOutput();
        $command->run(new ArrayInput(['--json' => true]), $output);

        $rows = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($rows);

        $plugin = 'kleinweb-site-news';
        self::assertSame([null, $plugin, null, $plugin], array_column($rows, 'site_plugin'));
        self::assertSame('kleinweb-base', $rows[0]['theme']);
        self::assertSame('kleinweb-news (kleinweb-base)', $rows[1]['theme']);
        $statuses = array_column($rows, 'status');
        self::assertSame(['ok', 'duplicate key', 'no key', 'duplicate key'], $statuses);
    }
}
