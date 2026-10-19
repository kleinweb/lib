<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Config;

use Illuminate\Contracts\Config\Repository;
use stdClass;

use function array_is_list;
use function array_key_exists;
use function array_reverse;
use function explode;
use function is_array;
use function strrpos;
use function substr;

/**
 * Applies per-site values on top of the network's config, reversibly.
 *
 * Values are applied leaf by leaf, so only the keys an overlay names are
 * touched; other values under the same config file, including ones changed at
 * runtime by Acorn (such as `cache.prefix` on `switch_blog`), are left alone.
 *
 * Merge rules:
 *
 * - associative arrays merge recursively;
 * - lists, including empty arrays, replace the existing value;
 * - `null` removes the key.
 *
 * Reverting removes branches an overlay created, except at the top level:
 * Laravel's config repository cannot unset a top-level key, so a config file
 * name that only an overlay introduced is left set to `null`.
 */
final class ConfigOverlay
{
    /** @var array<string, mixed> dotted key => value before the overlay */
    private array $originals = [];

    private readonly stdClass $missing;

    public function __construct(private readonly Repository $config)
    {
        $this->missing = new stdClass();
    }

    /**
     * Flatten nested overlay values to dotted leaf keys.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function leaves(array $values, string $prefix = ''): array
    {
        $leaves = [];

        foreach ($values as $key => $value) {
            $path = ($prefix === '') ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value) && $value !== [] && !array_is_list($value)) {
                $leaves += self::leaves($value, $path);
            } else {
                $leaves[$path] = $value;
            }
        }

        return $leaves;
    }

    /**
     * @param array<string, mixed> $leaves dotted key => value; `null` removes
     */
    public function apply(array $leaves): void
    {
        foreach ($leaves as $key => $value) {
            $this->remember($key);

            if ($value === null) {
                $this->forget($key);
            } else {
                $this->config->set($key, $value);
            }
        }
    }

    /**
     * Restore every value changed since the last revert.
     */
    public function revert(): void
    {
        foreach (array_reverse($this->originals, true) as $key => $original) {
            if ($original === $this->missing) {
                $this->forget($key);
            } else {
                $this->config->set($key, $original);
            }
        }

        $this->originals = [];
    }

    public function isApplied(): bool
    {
        return $this->originals !== [];
    }

    /**
     * Record the value to restore for a key.
     *
     * For a key that does not exist yet, the shallowest missing ancestor is
     * recorded instead, so reverting removes the whole branch the overlay
     * created rather than leaving empty arrays behind.
     */
    private function remember(string $key): void
    {
        if (array_key_exists($key, $this->originals)) {
            return;
        }

        if ($this->config->has($key)) {
            $this->originals[$key] = $this->config->get($key);

            return;
        }

        $path = '';
        foreach (explode('.', $key) as $segment) {
            $path = ($path === '') ? $segment : "{$path}.{$segment}";
            if (!$this->config->has($path)) {
                break;
            }
        }

        if (!array_key_exists($path, $this->originals)) {
            $this->originals[$path] = $this->missing;
        }
    }

    private function forget(string $key): void
    {
        $dot = strrpos($key, '.');
        if ($dot === false) {
            $this->config->set($key, null);

            return;
        }

        $parentKey = substr($key, 0, $dot);
        $parent = $this->config->get($parentKey);
        if (!is_array($parent)) {
            return;
        }

        unset($parent[substr($key, $dot + 1)]);
        $this->config->set($parentKey, $parent);
    }
}
