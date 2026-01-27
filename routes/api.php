<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes are prefixed with 'api/v1' in bootstrap/app.php
require __DIR__ . '/api-auth.php';
require __DIR__ . '/api-admin.php';
require __DIR__ . '/api-client.php';
