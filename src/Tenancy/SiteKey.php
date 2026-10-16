<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Support\Str;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use League\Uri\Components\Domain as UriDomain;
use Stringable;
use Throwable;

use function ctype_digit;
use function explode;
use function in_array;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Stable identifier for a site in a multisite network.
 *
 * Unlike blog IDs, keys are the same in every environment, so code and
 * configuration may depend on them.  A key is a lowercase DNS label because
 * keys are derived from hostnames.
 */
final readonly class SiteKey implements Stringable
{
    public const string PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /** Labels that are valid but never identify a site. */
    public const array RESERVED = ['www'];

    private function __construct(public string $value) {}

    /**
     * @throws InvalidSiteKey
     */
    public static function from(string $value): self
    {
        if (!self::isValid($value)) {
            throw InvalidSiteKey::forValue($value);
        }

        return new self($value);
    }

    public static function tryFrom(?string $value): ?self
    {
        return ($value !== null && self::isValid($value)) ? new self($value) : null;
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * Derive a key from a hostname.
     *
     * Uses the same rule as {@see \Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains}:
     * the leftmost label of the host, so `newshub.edu` gives `newshub` and
     * `www.newshub.edu` gives `www` (which is reserved).
     */
    public static function fromHost(string $host): ?self
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        try {
            $label = UriDomain::new($host)->withRootLabel()->get(-1);
        } catch (Throwable) {
            return null;
        }

        return self::tryFrom($label);
    }

    /**
     * Derive a key from the first segment of a site path.
     *
     * Used for subdirectory sites, such as `/newshub/` on the main host.
     */
    public static function fromPath(string $path): ?self
    {
        $segment = explode('/', trim($path, '/'), 2)[0];

        return self::tryFrom(strtolower($segment));
    }

    public function isReserved(): bool
    {
        return in_array($this->value, self::RESERVED, true);
    }

    /**
     * Whether the key can be used as a PHP namespace segment.
     */
    public function isNamespaceable(): bool
    {
        return !ctype_digit($this->value[0]);
    }

    /**
     * Namespace and directory segment for the key, e.g. `news-hub` gives `NewsHub`.
     *
     * @throws InvalidSiteKey if the key starts with a digit
     */
    public function studly(): string
    {
        if (!$this->isNamespaceable()) {
            throw InvalidSiteKey::notNamespaceable($this->value);
        }

        return Str::studly($this->value);
    }

    public function equals(self|string $other): bool
    {
        return (string) $other === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
