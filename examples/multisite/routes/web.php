<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Routes every site has.  A site's own routes live in its site plugin
// (kleinweb-site-news/routes/web.php) and load only on that site.

Route::get('/healthz', static fn () => response()->json(['ok' => true]));
