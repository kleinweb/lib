<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A job that needs its site's own bindings, so must run on a worker started
 * for that site.
 *
 * Most jobs do not: one worker serves the network, Acorn switches to the
 * job's blog, and data and config follow.  A job that resolves services a
 * site plugin or theme binds does, because a worker has only the providers
 * of the site it was started for.  Use {@see RunsOnSiteWorker} to route it
 * to its site's queue; a worker for another site fails it instead of
 * running it with the wrong bindings.
 */
interface ShouldRunOnSiteWorker extends ShouldQueue {}
