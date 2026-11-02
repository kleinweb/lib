<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Kleinweb\Lib\Tenancy\Domain;
use Kleinweb\Lib\Tenancy\Exceptions\InvalidSiteKey;
use Kleinweb\Lib\Tenancy\SiteKey;

it('takes the leftmost label of a host', function (string $host, ?string $expected): void {
    expect(SiteKey::fromHost($host)?->value)->toBe($expected);
})->with([
    'domain' => ['newshub.edu', 'newshub'],
    'subdomain' => ['news.temple.edu', 'news'],
    'case' => ['NewsHub.EDU', 'newshub'],
    'www is a label' => ['www.newshub.edu', 'www'],
    'single label' => ['localhost', 'localhost'],
    'leading digit' => ['9lives.org', '9lives'],
    'leading hyphen' => ['-bad.org', null],
    'empty' => ['', null],
]);

it('takes the first segment of a path', function (string $path, ?string $expected): void {
    expect(SiteKey::fromPath($path)?->value)->toBe($expected);
})->with([
    'slashes' => ['/newshub/', 'newshub'],
    'root' => ['/', null],
    'bare' => ['news-hub', 'news-hub'],
    'nested' => ['/a/b/', 'a'],
]);

it('is a single lowercase DNS label', function (string $value, bool $valid): void {
    expect(SiteKey::isValid($value))->toBe($valid);
})->with([
    'hyphenated' => ['news-hub', true],
    'one letter' => ['a', true],
    'leading hyphen' => ['-x', false],
    'trailing hyphen' => ['x-', false],
    'upper case' => ['Abc', false],
    '63 characters' => [str_repeat('a', 63), true],
    '64 characters' => [str_repeat('a', 64), false],
]);

it('reserves www', function (): void {
    expect(SiteKey::from('www')->isReserved())->toBeTrue()
        ->and(SiteKey::from('news')->isReserved())->toBeFalse()
    ;
});

it('studly-cases keys for namespaces', function (): void {
    expect(SiteKey::from('news-hub')->studly())->toBe('NewsHub')
        ->and(SiteKey::from('9lives')->isNamespaceable())->toBeFalse()
    ;
});

it('refuses to studly-case a key with a leading digit', function (): void {
    SiteKey::from('9x')->studly();
})->throws(InvalidSiteKey::class);

it('normalizes domains', function (): void {
    expect((string) new Domain('Example.COM', 'newshub'))->toBe('example.com/newshub')
        ->and((string) new Domain('example.com', '/'))->toBe('example.com')
        ->and((new Domain('e.com', '//a//'))->path)->toBe('/a/')
    ;
});
