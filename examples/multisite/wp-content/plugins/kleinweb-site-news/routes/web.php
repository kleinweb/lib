<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kleinweb\Sites\News\Http\Controllers\DeskController;

// Loaded only on the news site, and only when routes are not cached
// (route:cache is network-wide and cannot hold one site's routes).

Route::get('/desks.json', [DeskController::class, 'index']);
