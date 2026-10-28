<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Exceptions;

use LogicException;

use function sprintf;

/**
 * A site plugin was activated.  Site plugins are never activated: each loads
 * automatically on its own site.
 */
final class SitePluginActivation extends LogicException
{
    public function __construct(public readonly string $plugin, public readonly bool $networkWide)
    {
        parent::__construct(sprintf(
            'Site plugin "%s" cannot be activated%s: it loads automatically on its own site.',
            $plugin,
            $networkWide ? ' network-wide' : '',
        ));
    }
}
