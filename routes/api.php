<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    require __DIR__ . '/api-auth.php';
    require __DIR__ . '/api-admin.php';
    require __DIR__ . '/api-client.php';
});
