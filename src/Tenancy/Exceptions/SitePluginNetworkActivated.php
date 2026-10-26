<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Exceptions;

use LogicException;

use function sprintf;

final class SitePluginNetworkActivated extends LogicException
{
    public function __construct(public readonly string $plugin)
    {
        parent::__construct(sprintf(
            'Site plugin "%s" cannot be network-activated; activate it on its own site only.',
            $plugin,
        ));
    }
}
