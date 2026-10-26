<?php

declare(strict_types=1);

/*
 * Post types and taxonomies every site has.
 *
 * Same format as roots/acorn-post-types (Extended CPTs arguments).  A site
 * plugin's config/post-types.php adds to this, changes arguments leaf by
 * leaf, or removes a type by setting it to null.
 */

return [
    'post_types' => [
        'profile' => [
            'menu_icon' => 'dashicons-id',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
            'names' => [
                'singular' => 'Profile',
                'plural' => 'Profiles',
                'slug' => 'people',
            ],
        ],
    ],

    'taxonomies' => [
        'topic' => [
            'post_types' => ['post', 'profile'],
            'meta_box' => 'simple',
            'show_in_rest' => true,
            'names' => [
                'singular' => 'Topic',
                'plural' => 'Topics',
            ],
        ],
    ],
];
