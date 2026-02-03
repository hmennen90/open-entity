<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('app');
});

// Catch-all für Vue Router (SPA)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
