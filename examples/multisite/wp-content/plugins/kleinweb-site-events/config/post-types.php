<?php

declare(strict_types=1);

/*
 * Overlays the shared config/post-types.php on the events site.
 */

return [
    'post_types' => [
        'event' => [
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
            'has_archive' => true,
            'names' => [
                'singular' => 'Event',
                'plural' => 'Events',
                'slug' => 'events',
            ],
        ],
        // The shared type does not exist on this site.
        'profile' => null,
    ],

    'taxonomies' => [
        'topic' => [
            'post_types' => ['post', 'event'],
        ],
    ],
];
