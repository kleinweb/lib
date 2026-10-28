<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

use Illuminate\Queue\Jobs\FakeJob;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * A queued job as a worker sees it: a payload, and a record of failure.
 */
final class PayloadJob extends FakeJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload) {}

    public function getRawBody(): string
    {
        return json_encode($this->payload, JSON_THROW_ON_ERROR);
    }
}
