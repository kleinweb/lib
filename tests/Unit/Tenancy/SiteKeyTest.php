<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy;

use Kleinweb\Lib\Tenancy\Domain;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use Kleinweb\Lib\Tenancy\SiteKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class SiteKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function hosts(): iterable
    {
        yield 'domain' => ['newshub.edu', 'newshub'];
        yield 'subdomain' => ['news.temple.edu', 'news'];
        yield 'case' => ['NewsHub.EDU', 'newshub'];
        yield 'www is a label' => ['www.newshub.edu', 'www'];
        yield 'single label' => ['localhost', 'localhost'];
        yield 'leading digit' => ['9lives.org', '9lives'];
        yield 'leading hyphen' => ['-bad.org', null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('hosts')]
    public function testFromHostTakesTheLeftmostLabel(string $host, ?string $expected): void
    {
        self::assertSame($expected, SiteKey::fromHost($host)?->value);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function paths(): iterable
    {
        yield 'slashes' => ['/newshub/', 'newshub'];
        yield 'root' => ['/', null];
        yield 'bare' => ['news-hub', 'news-hub'];
        yield 'nested' => ['/a/b/', 'a'];
    }

    #[DataProvider('paths')]
    public function testFromPathTakesTheFirstSegment(string $path, ?string $expected): void
    {
        self::assertSame($expected, SiteKey::fromPath($path)?->value);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function values(): iterable
    {
        yield 'hyphenated' => ['news-hub', true];
        yield 'one letter' => ['a', true];
        yield 'leading hyphen' => ['-x', false];
        yield 'trailing hyphen' => ['x-', false];
        yield 'upper case' => ['Abc', false];
        yield '63 characters' => [str_repeat('a', 63), true];
        yield '64 characters' => [str_repeat('a', 64), false];
    }

    #[DataProvider('values')]
    public function testIsAOneLabelDnsName(string $value, bool $valid): void
    {
        self::assertSame($valid, SiteKey::isValid($value));
    }

    public function testWwwIsReserved(): void
    {
        self::assertTrue(SiteKey::from('www')->isReserved());
        self::assertFalse(SiteKey::from('news')->isReserved());
    }

    public function testStudlyCaseForNamespaces(): void
    {
        self::assertSame('NewsHub', SiteKey::from('news-hub')->studly());
        self::assertFalse(SiteKey::from('9lives')->isNamespaceable());
    }

    public function testStudlyRefusesALeadingDigit(): void
    {
        $this->expectException(InvalidSiteKey::class);
        SiteKey::from('9x')->studly();
    }

    public function testDomainNormalizes(): void
    {
        self::assertSame('example.com/newshub', (string) new Domain('Example.COM', 'newshub'));
        self::assertSame('example.com', (string) new Domain('example.com', '/'));
        self::assertSame('/a/', (new Domain('e.com', '//a//'))->path);
    }
}
