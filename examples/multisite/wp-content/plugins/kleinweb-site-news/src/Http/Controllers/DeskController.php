<?php

declare(strict_types=1);

namespace Kleinweb\Sites\News\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class DeskController
{
    public function index(): JsonResponse
    {
        $terms = get_terms(['taxonomy' => 'desk', 'hide_empty' => false, 'fields' => 'slugs']);

        return response()->json(is_array($terms) ? $terms : []);
    }
}
