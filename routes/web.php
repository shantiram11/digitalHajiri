<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| SPA catch-all. Every non-/api, non-/horizon path returns the Vue shell,
| and Vue Router resolves the rest client-side.
*/
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|horizon|up).*$');
