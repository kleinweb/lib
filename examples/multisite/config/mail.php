<?php

declare(strict_types=1);

/*
 * Shared mail settings (trimmed).  kleinweb-site-news/config/mail.php
 * overlays `from` on the news site only.
 */

return [
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [
        'log' => [
            'transport' => 'log',
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'web@klein.temple.edu'),
        'name' => env('MAIL_FROM_NAME', 'Klein College'),
    ],
];
