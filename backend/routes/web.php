<?php

use Illuminate\Support\Facades\Route;

// One address for everything: the built Angular app is served from public/app
Route::get('/', fn () => redirect('/app/'));
Route::get('/app/{any?}', function () {
    $index = public_path('app/index.html');
    abort_unless(is_file($index), 404, 'Frontend not installed (public/app/index.html missing)');
    return response()->file($index);
})->where('any', '.*');
