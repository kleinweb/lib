<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tests\Unit\Tenancy\Config;

use Illuminate\Config\Repository;
use Kleinweb\Lib\Tenancy\Config\ConfigOverlay;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_merge;

final class ConfigOverlayTest extends TestCase
{
    private const array BASE = [
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

    private Repository $config;

    private ConfigOverlay $overlay;

    protected function setUp(): void
    {
        $this->config = new Repository(self::BASE);
        $this->overlay = new ConfigOverlay($this->config);
        $this->overlay->apply(ConfigOverlay::leaves([
            'prettify' => ['relative-urls' => ['enabled' => true, 'hooks' => ['x']]],
            'mail' => ['from' => ['address' => 'news@example.edu']],
            'post-types' => ['post_types' => ['event' => null, 'story' => ['menu_icon' => 'doc']]],
            'services' => ['analytics' => ['id' => 'G-1']],
            'empty' => [],
        ]));
    }

    public function testMapsMergeLeafByLeaf(): void
    {
        self::assertTrue($this->config->get('prettify.clean-up.obscurity'));
        self::assertTrue($this->config->get('prettify.relative-urls.enabled'));
        self::assertSame('Example', $this->config->get('mail.from.name'));
        self::assertSame('news@example.edu', $this->config->get('mail.from.address'));
    }

    public function testListsReplace(): void
    {
        self::assertSame(['x'], $this->config->get('prettify.relative-urls.hooks'));
    }

    public function testNullRemovesAndNewKeysAreAdded(): void
    {
        $postTypes = $this->config->get('post-types.post_types');
        self::assertSame(['seed', 'story'], array_keys($postTypes));
        self::assertSame('G-1', $this->config->get('services.analytics.id'));
        self::assertSame([], $this->config->get('empty'));
    }

    public function testRevertRestoresEverythingItChanged(): void
    {
        $this->overlay->revert();

        // A top-level name only the overlay created is left null: the
        // repository cannot unset a top-level key.
        self::assertEquals(
            array_merge(self::BASE, ['services' => null, 'empty' => null]),
            $this->config->all(),
        );
    }

    public function testRevertKeepsChangesToKeysItDidNotSet(): void
    {
        // As Acorn does on switch_blog while an overlay is applied.
        $this->config->set('cache.prefix', 'blog_2_');
        $this->overlay->revert();

        self::assertSame('blog_2_', $this->config->get('cache.prefix'));
    }
}
