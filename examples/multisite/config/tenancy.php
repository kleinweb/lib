<?php

declare(strict_types=1);

/*
 * Optional.  Every key has a default; this file only shows them.
 */

return [
    // Run for keyed sites on load and on every switch_to_blog() into them.
    'bootstrappers' => [
        Kleinweb\Lib\Tenancy\Bootstrappers\ConfigBootstrapper::class,
    ],

    // Run for every blog on load and on every switch.
    'blog_bootstrappers' => [
        Kleinweb\Lib\Tenancy\Bootstrappers\CacheBootstrapper::class,
    ],

    // Blogmeta values applied as config on top of the site plugin's files,
    // for settings editors change per site without a deploy.
    'meta_config' => [
        'contact_email' => 'mail.from.address',
    ],
];
