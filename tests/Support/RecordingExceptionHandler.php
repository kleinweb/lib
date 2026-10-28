<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

/**
 * Keeps reported exceptions; renders nothing.
 */
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter -- Contract signatures.
final class RecordingExceptionHandler implements ExceptionHandler
{
    /** @var list<Throwable> */
    public array $reported = [];

    public function report(Throwable $e): void
    {
        $this->reported[] = $e;
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    /**
     * @param \Illuminate\Http\Request $request
     */
    public function render($request, Throwable $e): never
    {
        throw $e;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function renderForConsole($output, Throwable $e): void
    {
        // Not needed.
    }
}
