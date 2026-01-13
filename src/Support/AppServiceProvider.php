<?php

// SPDX-FileCopyrightText: (C) 2025-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Kleinweb\Lib\Console\Commands\Attachment\DeleteDead as DeleteDeadAttachments;
use Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains;
use Kleinweb\Lib\Hooks\Attributes\Filter;
use Kleinweb\Lib\Support\ServiceProvider as ServiceProviderBase;

abstract class AppServiceProvider extends ServiceProviderBase
{
    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            DeleteDeadAttachments::class,
            DemapDomains::class,
        ]);
    }

    /**
     * @param array<string, string> $mimes allowed MIME types for upload
     *
     * @return array<string, string>
     */
    #[Filter('upload_mimes')]
    public function blockAudioVideoUploads($mimes): array
    {
        // Video formats (from `wp_get_mime_types()`).
        unset(
            $mimes['asf|asx'],
            $mimes['wmv'],
            $mimes['wmx'],
            $mimes['wm'],
            $mimes['avi'],
            $mimes['divx'],
            $mimes['flv'],
            $mimes['mov|qt'],
            $mimes['mpeg|mpg|mpe'],
            $mimes['mp4|m4v'],
            $mimes['ogv'],
            $mimes['webm'],
            $mimes['mkv'],
            $mimes['3gp|3gpp'],
            $mimes['3g2|3gp2'],
        );

        // Audio formats (from `wp_get_mime_types()`).
        unset(
            $mimes['mp3|m4a|m4b'],
            $mimes['aac'],
            $mimes['ra|ram'],
            $mimes['wav|x-wav'],
            $mimes['ogg|oga'],
            $mimes['flac'],
            $mimes['mid|midi'],
            $mimes['wma'],
            $mimes['wax'],
            $mimes['mka'],
        );

        return $mimes;
    }
}
