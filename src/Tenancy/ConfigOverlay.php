<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Contracts\Config\Repository;
use stdClass;

/**
 * Reversibly apply per-site values over application-level config.
 *
 * ## Merge Rules
 *
 * - Associative arrays merge recursively
 * - Lists (incl. empty arrays) replace existing value
 * - `null` deletes the key
 */
final class ConfigOverlay
{
    /** @var array<string, mixed> dotted key => pre-overlay value */
    private array $originals = [];

    private readonly stdClass $missing;

    public function __construct(private readonly Repository $config)
    {
        $this->missing = new stdClass();
    }

    /**
     * @param array<string, mixed> $leaves dotted key => value; null to delete
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
     * Restore every value changed since the previous revert.
     *
     * Reverting removes branches created by a config overlay,
     * except at the root level.
     */
    public function revert(): void
    {
        $originals = array_reverse($this->originals, true);

        foreach ($originals as $key => $original) {
            if ($original === $this->missing) {
                $this->forget($key);
            } else {
                $this->config->set($key, $original);
            }
        }

        $this->originals = [];
    }

    /**
     * Record the value to restore for a given key.
     *
     * When the given key does not yet exist, record the shallowest ("first")
     * missing ancestor so that {@see ConfigOverlay::forget()} removes the
     * entire branch rather than leaving a mess of empty arrays.
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

        $path = array_reduce(
            explode('.', $key),
            function (string $acc, string $cur): string {
                $next = "{$acc}.{$cur}";

                if (!$this->config->has($next)) {
                    return $acc;
                }

                return $next;
            },
            '',
        );

        // TODO: cleanup
        //
        //        $path = '';
        //        foreach (explode('.', $key) as $segment) {
        //            $path = ($path === '') ? $segment : "{$path}.{$segment}";
        //
        //            if (!$this->config->has($path)) {
        //                break;
        //            }
        //        }

        if (!array_key_exists($path, $this->originals)) {
            $this->originals[$path] = $this->missing;
        }
    }

    private function forget(string $key): void
    {
        $dotPos = strrpos($key, '.');

        if ($dotPos === false) {
            $this->config->set($key, null);

            return;
        }

        $parentKey = substr($key, 0, $dotPos);
        $parent = $this->config->get($parentKey);

        if (!is_array($parent)) {
            return;
        }

        $leafKey = substr($key, $dotPos + 1);

        unset($parent[$leafKey]);

        $this->config->set($parentKey, $parent);
    }

    /**
     * Flatten a config tree, rehousing values under full dotted keypaths.
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

            if (is_array($value) && ($value !== []) && ! array_is_list($value)) {
                $leaves += self::leaves($value, $path);
            } else {
                $leaves[$path] = $value;
            }
        }

        return $leaves;
    }
}
