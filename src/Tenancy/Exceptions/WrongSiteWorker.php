<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Exceptions;

use RuntimeException;

use function sprintf;

final class WrongSiteWorker extends RuntimeException
{
    public static function for(string $job, ?string $jobSite, ?string $workerSite): self
    {
        return new self(sprintf(
            '%s needs the bindings of site "%s" but ran on a worker for %s. '
            . 'Dispatch it with RunsOnSiteWorker and run '
            . '`wp --url=<site url> acorn queue:work --queue=site-<key>` for that site.',
            $job,
            $jobSite ?? '(no key)',
            ($workerSite !== null) ? "site \"{$workerSite}\"" : 'no keyed site',
        ));
    }
}
