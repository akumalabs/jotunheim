<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| SPA catch-all route - Vue Router handles all frontend routing
|
*/

// All routes are handled by the Vue SPA
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
