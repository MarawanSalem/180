<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// API-only application — no web views.
Route::get('/', fn () => abort(404));
