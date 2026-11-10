<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy;

use Illuminate\Support\Str;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use League\Uri\Components\Domain;
use Stringable;
use Throwable;

/**
 * Stable identifier for a site in a multisite network.
 *
 * A site key is identical across all environments, so it may be used in code.
 *
 * A site key is a lowercase DNS label derived from production hostnames.
 */
final class SiteKey implements Stringable
{
    public const string PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /**
     * Labels that are valid but never identify a site.
     */
    public const array RESERVED = ['www'];

    private function __construct(public string $value) {}

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
     * FIXME: Must use the same logic as {@see \Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains}
     */
    public static function fromHost(string $host): ?self
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        try {
            $label = Domain::new($host)->withRootLabel()->get(-1);
        } catch (Throwable $exception) {
            return null;
        }

        return self::tryFrom($label);
    }

    /**
     * Derive a site key from the first segment of a site path.
     *
     * Used for subdirectory sites, e.g. `/templeupdate/`.
     */
    public static function fromPath(string $path): ?self
    {
        $segments = explode('/', trim($path, '/'), 2);
        $segment = $segments[0];

        return self::tryFrom(strtolower($segment));
    }

    public function isReserved(): bool
    {
        return in_array($this->value, self::RESERVED, true);
    }

    public function isNamespaceable(): bool
    {
        return !ctype_digit($this->value[0]);
    }

    /**
     * Namespace and directory segment for the site key.
     *
     * For example, `news-ticker` yields `NewsTicker`.
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

    public function __toString()
    {
        return $this->value;
    }
}
