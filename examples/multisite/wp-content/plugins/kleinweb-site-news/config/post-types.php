<?php

declare(strict_types=1);

/*
 * Overlays the shared config/post-types.php on the news site.
 */

return [
    'post_types' => [
        'story' => [
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'author'],
            'show_in_rest' => true,
            'has_archive' => true,
            'names' => [
                'singular' => 'Story',
                'plural' => 'Stories',
                'slug' => 'stories',
            ],
        ],
        // Changes one argument of the shared type, leaving the rest.
        'profile' => [
            'menu_icon' => 'dashicons-businessperson',
        ],
    ],

    'taxonomies' => [
        'desk' => [
            'post_types' => ['story'],
            'show_in_rest' => true,
            'names' => [
                'singular' => 'Desk',
                'plural' => 'Desks',
            ],
        ],
        // Lists replace rather than merge: topics now apply to stories too.
        'topic' => [
            'post_types' => ['post', 'profile', 'story'],
        ],
    ],
];
