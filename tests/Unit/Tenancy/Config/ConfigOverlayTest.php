<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Illuminate\Config\Repository;
use Kleinweb\Lib\Tenancy\Config\ConfigOverlay;

const OVERLAY_BASE = [
    'prettify' => [
        'clean-up' => ['enabled' => true, 'obscurity' => true],
        'relative-urls' => ['enabled' => false, 'hooks' => ['a', 'b', 'c']],
    ],
    'mail' => [
        'from' => ['address' => 'hello@example.com', 'name' => 'Example'],
        'mailers' => ['smtp' => ['host' => 'smtp.x', 'port' => 25]],
    ],
    'post-types' => [
        'post_types' => ['seed' => ['menu_icon' => 'star'], 'event' => ['menu_icon' => 'cal']],
    ],
    'cache' => ['prefix' => 'blog_1_'],
];

beforeEach(function (): void {
    $this->config = new Repository(OVERLAY_BASE);
    $this->overlay = new ConfigOverlay($this->config);
    $this->overlay->apply(ConfigOverlay::leaves([
        'prettify' => ['relative-urls' => ['enabled' => true, 'hooks' => ['x']]],
        'mail' => ['from' => ['address' => 'news@example.edu']],
        'post-types' => ['post_types' => ['event' => null, 'story' => ['menu_icon' => 'doc']]],
        'services' => ['analytics' => ['id' => 'G-1']],
        'empty' => [],
    ]));
});

it('merges maps leaf by leaf', function (): void {
    expect($this->config->get('prettify.clean-up.obscurity'))->toBeTrue()
        ->and($this->config->get('prettify.relative-urls.enabled'))->toBeTrue()
        ->and($this->config->get('mail.from.name'))->toBe('Example')
        ->and($this->config->get('mail.from.address'))->toBe('news@example.edu')
    ;
});

it('replaces lists', function (): void {
    expect($this->config->get('prettify.relative-urls.hooks'))->toBe(['x']);
});

it('removes with null and adds new keys', function (): void {
    expect(array_keys($this->config->get('post-types.post_types')))->toBe(['seed', 'story'])
        ->and($this->config->get('services.analytics.id'))->toBe('G-1')
        ->and($this->config->get('empty'))->toBe([])
    ;
});

it('reverts everything it changed', function (): void {
    $this->overlay->revert();

    // A top-level name only the overlay created is left null: the
    // repository cannot unset a top-level key.
    expect($this->config->all())->toEqual([...OVERLAY_BASE, 'services' => null, 'empty' => null]);
});

it('keeps changes to keys it did not set', function (): void {
    // As Acorn does on switch_blog while an overlay is applied.
    $this->config->set('cache.prefix', 'blog_2_');
    $this->overlay->revert();

    expect($this->config->get('cache.prefix'))->toBe('blog_2_');
});
