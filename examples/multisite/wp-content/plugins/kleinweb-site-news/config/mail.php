<?php

declare(strict_types=1);

/*
 * Overlays the shared config/mail.php on the news site: only these leaves
 * change; mail.default and mail.mailers stay as shared.
 */

return [
    'from' => [
        'address' => 'news@klein.temple.edu',
        'name' => 'Klein News',
    ],
];
