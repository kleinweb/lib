<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support\Commands;

use Closure;
use Illuminate\Console\Command;
use Kleinweb\Lib\Console\Concerns\InteractsWithSites;
use Kleinweb\Lib\Database\Models\Site;

/**
 * Runs a callback given by the test for every selected site.
 */
final class VisitSites extends Command
{
    use InteractsWithSites;

    /** @var string */
    protected $signature = 'test:visit-sites
        {--sites=* : Only these site keys}
        {--skip-sites=* : Skip these site keys}
        {--no-content-types : Leave post types alone}';

    /**
     * @param Closure(Site): mixed $visit
     */
    public function __construct(private readonly Closure $visit)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->forEachSite($this->visit, !$this->option('no-content-types'));
    }
}
