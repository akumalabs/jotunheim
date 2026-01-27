<?php

use App\Http\Controllers\Api\Client\BackupController;
use App\Http\Controllers\Api\Client\FirewallController;
use App\Http\Controllers\Api\Client\GuestAgentController;
use App\Http\Controllers\Api\Client\ServerController;
use App\Http\Controllers\Api\Client\SshKeyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Client
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::prefix('client')->group(function () {
        Route::apiResource('servers', ServerController::class);
        Route::get('/servers/{uuid}', [ServerController::class, 'show']);
        Route::get('/servers/{uuid}/status', [ServerController::class, 'status']);
        Route::post('/servers/{uuid}/power', [ServerController::class, 'power']);
        Route::get('/servers/{uuid}/console', [ServerController::class, 'console']);
        Route::post('/servers/{uuid}/settings/password', [ServerController::class, 'updatePassword']);
        Route::post('/servers/{uuid}/settings/iso/mount', [ServerController::class, 'mountIso']);
        Route::post('/servers/{uuid}/settings/iso/unmount', [ServerController::class, 'unmountIso']);

        Route::prefix('/servers/{uuid}/snapshots')->group(function () {
            Route::get('/', [ServerController::class, 'listSnapshots']);
            Route::post('/', [ServerController::class, 'createSnapshot']);
            Route::post('/{name}/rollback', [ServerController::class, 'rollbackSnapshot']);
            Route::delete('/{name}', [ServerController::class, 'deleteSnapshot']);
        });

        Route::post('/servers/{uuid}/settings/reinstall', [ServerController::class, 'reinstall']);

        Route::prefix('/servers/{uuid}/backups')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/', [BackupController::class, 'store']);
            Route::delete('/{backup}', [BackupController::class, 'destroy']);
            Route::post('/{backup}/restore', [BackupController::class, 'restore']);
            Route::post('/{backup}/lock', [BackupController::class, 'toggleLock']);
        });

        Route::apiResource('ssh-keys', SshKeyController::class);

        Route::prefix('/servers/{uuid}/firewall')->group(function () {
            Route::get('/', [FirewallController::class, 'index']);
            Route::post('/enable', [FirewallController::class, 'enable']);
            Route::post('/disable', [FirewallController::class, 'disable']);
            Route::post('/rules', [FirewallController::class, 'create']);
            Route::put('/rules/{pos}', [FirewallController::class, 'update']);
            Route::delete('/rules/{pos}', [FirewallController::class, 'destroy']);
            Route::post('/templates', [FirewallController::class, 'applyTemplate']);
        });

        Route::prefix('/servers/{uuid}/agent')->group(function () {
            Route::get('/info', [GuestAgentController::class, 'info']);
            Route::get('/os-info', [GuestAgentController::class, 'osInfo']);
            Route::get('/network', [GuestAgentController::class, 'network']);
            Route::post('/ping', [GuestAgentController::class, 'ping']);
            Route::post('/exec', [GuestAgentController::class, 'exec']);
            Route::post('/set-password', [GuestAgentController::class, 'setPassword']);
            Route::post('/shutdown', [GuestAgentController::class, 'shutdown']);
        });
    });
});
