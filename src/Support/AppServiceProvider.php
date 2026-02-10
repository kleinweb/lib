<?php

// SPDX-FileCopyrightText: (C) 2025-2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Support;

use Illuminate\Support\Facades\Request;
use Kleinweb\Lib\Console\Commands\Attachment\DeleteDead as DeleteDeadAttachments;
use Kleinweb\Lib\Console\Commands\Tenancy\DemapDomains;
use Kleinweb\Lib\Hooks\Attributes\Action;
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

    #[Action('wp_dashboard_setup')]
    public function dashboardCleanup(): void
    {
        // phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps -- Necessary.

        global $wp_meta_boxes;

        // "Activity" -- Redundant alongside Simple History.
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity']);

        // "Quick Draft" -- Useless anti-feature.  May cause 'Auto
        // Draft' creation (and notification email) on initial login.
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);

        // "WordPress Events and News" -- Spam.
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);

        // phpcs:enable Squiz.NamingConventions.ValidVariableName.NotCamelCaps
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

    /**
     * @param array<string, mixed> $data
     */
    #[Action('wp_mail_succeeded')]
    public function logSentMail($data): void
    {
        if (!function_exists('SimpleLogger')) {
            return;
        }

        $eventName = 'wp_mail_succeeded';

        $context = [
            '_message_key' => $eventName,
            '_server_request_method' => Request::method(),
            // https://simple-history.com/docs/logging-api/#automatic-grouping-of-messages
            '_occasionsID' => $eventName,
            'recipient' => implode(',', $data['to']),
            'subject' => $data['subject'],
            // NOTE: For security/privacy reasons, avoid storing
            // message content!  For example: a user with access to
            // Simple History could intercept a requested password
            // reset URL and change the recipient user's password.
            // 'message' => $data['message'],
        ];

        if (Request::userAgent()) {
            $context['_user_agent'] = Request::userAgent();
        }

        \SimpleLogger()->info('Mail sent to {recipient} with subject "{subject}"', $context);
    }
}
