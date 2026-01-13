<?php

// SPDX-FileCopyrightText: (C) 2025-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Log;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

final class SimpleHistoryHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $level = strtolower($record->level->getName());

        \SimpleLogger()->$level($record->message, $record->context);
    }
}
