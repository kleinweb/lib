<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Support;

use RuntimeException;

/**
 * Thrown by the `wp_die()` stand-in.
 */
final class WpDie extends RuntimeException {}
