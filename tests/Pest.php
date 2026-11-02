<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Kleinweb\Lib\Tests\Support\TestCase;

// Every test starts from a fresh fake WordPress (see TestCase::setUp()).
pest()->extend(TestCase::class)->in('Unit');
