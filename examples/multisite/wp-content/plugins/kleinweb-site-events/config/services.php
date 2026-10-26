<?php

declare(strict_types=1);

/*
 * Site-only settings need no shared file to overlay: this becomes
 * config('services.ticketing.*') on the events site and is absent elsewhere.
 */

return [
    'ticketing' => [
        'endpoint' => env('EVENTS_TICKETING_ENDPOINT', 'https://tickets.example.test/api'),
    ],
];
