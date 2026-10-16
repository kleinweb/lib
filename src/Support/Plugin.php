<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Webmozart\Assert\Assert;

use function ltrim;
use function plugin_basename;
use function plugin_dir_path;
use function plugin_dir_url;

final readonly class Plugin
{
    public string $basename;
    public string $name;
    public string $path;
    public string $url;
    public string $version;

    final private function __construct(public string $file)
    {
        $this->basename = $this->basename();
        $this->name = $this->basename;
        $this->path = $this->path();
        $this->url = $this->url();
        $this->version = $this->version();
    }

    /**
     * Basename for the plugin.
     */
    final public function basename(): string
    {
        return plugin_basename($this->file);
    }

    /**
     * URL for a file or directory within this plugin.
     */
    final public function url(string $file = ''): string
    {
        return plugin_dir_url($this->file) . ltrim($file, '/');
    }

    /**
     * Filesystem path for a file or directory within this plugin.
     */
    final public function path(string $file = ''): string
    {
        return plugin_dir_path($this->file) . ltrim($file, '/');
    }

    /**
     * Read version number from plugin headers.
     */
    final public function version(): string
    {
        $data = get_file_data($this->file, ['Version' => 'Version']);
        $version = $data['Version'] ?? '';

        Assert::stringNotEmpty($version);

        return $version;
    }
}
