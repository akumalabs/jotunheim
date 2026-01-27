<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AddressPoolController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\FirewallController;
use App\Http\Controllers\Api\Admin\LocationController;
use App\Http\Controllers\Api\Admin\NodeController;
use App\Http\Controllers\Api\Admin\RdnsController;
use App\Http\Controllers\Api\Admin\ServerController;
use App\Http\Controllers\Api\Admin\TemplateController;
use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::apiResource('locations', LocationController::class);

        Route::apiResource('nodes', NodeController::class);
        Route::post('/nodes/{node}/test', [NodeController::class, 'testConnection']);
        Route::post('/nodes/{node}/sync', [NodeController::class, 'sync']);
        Route::get('/nodes/{node}/stats', [NodeController::class, 'stats']);
        Route::get('/nodes/{node}/templates', [TemplateController::class, 'byNode']);
        Route::post('/nodes/{node}/templates/sync', [TemplateController::class, 'sync']);
        Route::get('/nodes/{node}/addresses/available', [AddressPoolController::class, 'available']);
        Route::get('/nodes/{node}/servers-unmanaged', [ServerController::class, 'unmanaged']);

        Route::apiResource('servers', ServerController::class);
        Route::post('/servers/{server}/power', [ServerController::class, 'power']);
        Route::get('/servers/{server}/status', [ServerController::class, 'status']);
        Route::get('/servers/{server}/install-progress', [ServerController::class, 'installProgress']);

        Route::prefix('/servers/{server}/network')->group(function () {
            Route::get('/available-ips', [ServerController::class, 'availableIPs']);
            Route::post('/assign-ip', [ServerController::class, 'assignIP']);
            Route::delete('/addresses/{address}', [ServerController::class, 'removeIP']);
            Route::post('/addresses/{address}/set-primary', [ServerController::class, 'setPrimaryIP']);
            Route::post('/update', [ServerController::class, 'updateNetwork']);
        });

        Route::prefix('/servers/{server}/snapshots')->group(function () {
            Route::get('/', [ServerController::class, 'snapshots']);
            Route::post('/', [ServerController::class, 'createSnapshot']);
            Route::post('/{name}/rollback', [ServerController::class, 'rollbackSnapshot']);
            Route::delete('/{name}', [ServerController::class, 'deleteSnapshot']);
        });

        Route::post('/servers/{server}/iso/mount', [ServerController::class, 'mountIso']);
        Route::post('/servers/{server}/iso/unmount', [ServerController::class, 'unmountIso']);
        Route::post('/servers/{server}/rebuild', [ServerController::class, 'rebuild']);
        Route::patch('/servers/{server}/resources', [ServerController::class, 'updateResources']);
        Route::get('/servers/{server}/rrd', [ServerController::class, 'rrdData']);
        Route::post('/servers/{server}/reset-password', [ServerController::class, 'resetPassword']);

        Route::prefix('/servers/{server}/firewall')->group(function () {
            Route::get('/', [FirewallController::class, 'index']);
            Route::post('/enable', [FirewallController::class, 'enable']);
            Route::post('/disable', [FirewallController::class, 'disable']);
            Route::post('/rules', [FirewallController::class, 'store']);
            Route::put('/rules/{rule}', [FirewallController::class, 'update']);
            Route::delete('/rules/{rule}', [FirewallController::class, 'destroy']);
            Route::post('/rulesets/{template}', [FirewallController::class, 'applyRuleset']);
        });

        Route::apiResource('users', UserController::class);

        Route::get('/templates', [TemplateController::class, 'index']);
        Route::post('/template-groups', [TemplateController::class, 'storeGroup']);
        Route::put('/template-groups/{group}', [TemplateController::class, 'updateGroup']);
        Route::delete('/template-groups/{group}', [TemplateController::class, 'destroyGroup']);
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::put('/templates/{template}', [TemplateController::class, 'update']);
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy']);

        Route::apiResource('address-pools', AddressPoolController::class);
        Route::post('/address-pools/{address_pool}/addresses', [AddressPoolController::class, 'addAddresses']);
        Route::post('/address-pools/{address_pool}/range', [AddressPoolController::class, 'addRange']);
        Route::delete('/addresses/{address}', [AddressPoolController::class, 'destroyAddress']);

        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/subject', [ActivityLogController::class, 'forSubject']);

        Route::apiResource('rdns-records', RdnsController::class);
        Route::post('/rdns-records/sync', [RdnsController::class, 'sync']);
        Route::post('/rdns-records/{id}/verify', [RdnsController::class, 'verify']);

        Route::prefix('settings')->group(function () {
            Route::post('/clear-cache', [App\Http\Controllers\Api\Admin\SettingsController::class, 'clearCache']);
            Route::post('/clear-route', [App\Http\Controllers\Api\Admin\SettingsController::class, 'clearRoute']);
            Route::post('/clear-config', [App\Http\Controllers\Api\Admin\SettingsController::class, 'clearConfig']);
            Route::post('/optimize', [App\Http\Controllers\Api\Admin\SettingsController::class, 'optimize']);
        });
    });
});
