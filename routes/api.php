<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AddressPoolController;
use App\Http\Controllers\Api\Admin\LocationController;
use App\Http\Controllers\Api\Admin\NodeController;
use App\Http\Controllers\Api\Admin\RdnsController;
use App\Http\Controllers\Api\Admin\ServerController as AdminServerController;
use App\Http\Controllers\Api\Admin\TemplateController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\Client\BackupController;
use App\Http\Controllers\Api\Client\FirewallController;
use App\Http\Controllers\Api\Client\GuestAgentController;
use App\Http\Controllers\Api\Client\ServerController as ClientServerController;
use App\Http\Controllers\Api\Client\ServerResizeController;
use App\Http\Controllers\Api\Client\SshKeyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api/v1
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

// Protected routes
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::patch('/user', [AuthController::class, 'update']);

        // Two-Factor Authentication routes
        Route::prefix('2fa')->middleware('auth:sanctum')->group(function () {
            Route::get('/setup', [TwoFactorController::class, 'setup']);
            Route::post('/verify', [TwoFactorController::class, 'verify']);
            Route::post('/disable', [TwoFactorController::class, 'disable']);
            Route::post('/recovery-codes', [TwoFactorController::class, 'generateRecoveryCodes']);
        });
    });

    // Admin routes (requires is_admin)
    Route::prefix('admin')->middleware('admin')->group(function () {
        // Dashboard Stats
        Route::get('/dashboard', [App\Http\Controllers\Api\Admin\DashboardController::class, 'index']);

        // Locations
        Route::apiResource('locations', LocationController::class);

        // Nodes
        Route::apiResource('nodes', NodeController::class);
        Route::post('/nodes/{node}/test', [NodeController::class, 'testConnection']);
        Route::post('/nodes/{node}/sync', [NodeController::class, 'sync']);
        Route::get('/nodes/{node}/stats', [NodeController::class, 'stats']);
        Route::get('/nodes/{node}/templates', [TemplateController::class, 'byNode']);
        Route::post('/nodes/{node}/templates/sync', [TemplateController::class, 'sync']);
        Route::get('/nodes/{node}/addresses/available', [AddressPoolController::class, 'available']);
        Route::get('/nodes/{node}/servers-unmanaged', [AdminServerController::class, 'unmanaged']);

        // Servers
        Route::apiResource('servers', AdminServerController::class);
        Route::post('/servers/{server}/power', [AdminServerController::class, 'power']);
        Route::get('/servers/{server}/status', [AdminServerController::class, 'status']);
        Route::get('/servers/{server}/install-progress', [AdminServerController::class, 'installProgress']);
        
        // Server Network Management
        Route::get('/servers/{server}/network/available-ips', [AdminServerController::class, 'availableIPs']);
        Route::post('/servers/{server}/network/assign-ip', [AdminServerController::class, 'assignIP']);
        Route::delete('/servers/{server}/network/addresses/{address}', [AdminServerController::class, 'removeIP']);
        Route::post('/servers/{server}/network/addresses/{address}/set-primary', [AdminServerController::class, 'setPrimaryIP']);
        Route::post('/servers/{server}/network/update', [AdminServerController::class, 'updateNetwork']);
        
        // Admin Server Management (Snapshots & ISO)
        Route::get('/servers/{server}/snapshots', [AdminServerController::class, 'snapshots']);
        Route::post('/servers/{server}/snapshots', [AdminServerController::class, 'createSnapshot']);
        Route::post('/servers/{server}/snapshots/{name}/rollback', [AdminServerController::class, 'rollbackSnapshot']);
        Route::delete('/servers/{server}/snapshots/{name}', [AdminServerController::class, 'deleteSnapshot']);
        Route::post('/servers/{server}/iso/mount', [AdminServerController::class, 'mountIso']);
        Route::post('/servers/{server}/iso/unmount', [AdminServerController::class, 'unmountIso']);
        Route::post('/servers/{server}/rebuild', [AdminServerController::class, 'rebuild']);
        Route::patch('/servers/{server}/resources', [AdminServerController::class, 'updateResources']);
        Route::get('/servers/{server}/rrd', [AdminServerController::class, 'rrdData']);
        Route::post('/servers/{server}/reset-password', [AdminServerController::class, 'resetPassword']);
        
        // Firewall Management
        Route::prefix('/servers/{server}/firewall')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Admin\FirewallController::class, 'index']);
            Route::post('/enable', [App\Http\Controllers\Api\Admin\FirewallController::class, 'enable']);
            Route::post('/disable', [App\Http\Controllers\Api\Admin\FirewallController::class, 'disable']);
            Route::post('/rules', [App\Http\Controllers\Api\Admin\FirewallController::class, 'store']);
            Route::put('/rules/{rule}', [App\Http\Controllers\Api\Admin\FirewallController::class, 'update']);
            Route::delete('/rules/{rule}', [App\Http\Controllers\Api\Admin\FirewallController::class, 'destroy']);
            Route::post('/rulesets/{template}', [App\Http\Controllers\Api\Admin\FirewallController::class, 'applyRuleset']);
        });



        // Users
        Route::apiResource('users', UserController::class);

        // Templates
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::post('/template-groups', [TemplateController::class, 'storeGroup']);
        Route::put('/template-groups/{group}', [TemplateController::class, 'updateGroup']);
        Route::delete('/template-groups/{group}', [TemplateController::class, 'destroyGroup']);
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::put('/templates/{template}', [TemplateController::class, 'update']);
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy']);

        // Address Pools
        Route::apiResource('address-pools', AddressPoolController::class);
        Route::post('/address-pools/{address_pool}/addresses', [AddressPoolController::class, 'addAddresses']);
        Route::post('/address-pools/{address_pool}/range', [AddressPoolController::class, 'addRange']);
        Route::delete('/addresses/{address}', [AddressPoolController::class, 'destroyAddress']);

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/subject', [ActivityLogController::class, 'forSubject']);

        // RDNS
        Route::apiResource('rdns-records', RdnsController::class);
        Route::post('/rdns-records/sync', [RdnsController::class, 'sync']);
        Route::post('/rdns-records/{id}/verify', [RdnsController::class, 'verify']);
        // Settings - System Actions
        Route::post('/settings/clear-cache', [App\Http\Controllers\Api\Admin\SettingsController::class, 'clearCache']);
        Route::post('/settings/clear-route', [App\Http\Controllers\Api\Admin\SettingsController::class, 'clearRoute']);
        Route::post('/settings/clear-config', [App\Http\Controllers\Api\Admin\SettingsController::class, 'clearConfig']);
        Route::post('/settings/optimize', [App\Http\Controllers\Api\Admin\SettingsController::class, 'optimize']);
    });

    // Client routes (authenticated users)
    Route::prefix('client')->group(function () {
        // Servers
        Route::get('/servers', [ClientServerController::class, 'index']);
        Route::get('/servers/{uuid}', [ClientServerController::class, 'show']);
        Route::get('/servers/{uuid}/status', [ClientServerController::class, 'status']);
        Route::post('/servers/{uuid}/power', [ClientServerController::class, 'power']);
        Route::get('/servers/{uuid}/console', [ClientServerController::class, 'console']);

        Route::post('/servers/{uuid}/settings/password', [ClientServerController::class, 'updatePassword']);
        Route::post('/servers/{uuid}/settings/iso/mount', [ClientServerController::class, 'mountIso']);
        Route::post('/servers/{uuid}/settings/iso/unmount', [ClientServerController::class, 'unmountIso']);

        // Server resize
        Route::post('/servers/{uuid}/settings/resize', [ServerResizeController::class, 'resize']);

        // Snapshots
        Route::get('/servers/{uuid}/snapshots', [ClientServerController::class, 'listSnapshots']);
        Route::post('/servers/{uuid}/snapshots', [ClientServerController::class, 'createSnapshot']);
        Route::post('/servers/{uuid}/snapshots/{name}/rollback', [ClientServerController::class, 'rollbackSnapshot']);
        Route::delete('/servers/{uuid}/snapshots/{name}', [ClientServerController::class, 'deleteSnapshot']);

        // Reinstall
        Route::post('/servers/{uuid}/settings/reinstall', [ClientServerController::class, 'reinstall']);

        // Backups
        Route::get('/servers/{uuid}/backups', [BackupController::class, 'index']);
        Route::post('/servers/{uuid}/backups', [BackupController::class, 'store']);
        Route::delete('/servers/{uuid}/backups/{backup}', [BackupController::class, 'destroy']);
        Route::post('/servers/{uuid}/backups/{backup}/restore', [BackupController::class, 'restore']);
        Route::post('/servers/{uuid}/backups/{backup}/lock', [BackupController::class, 'toggleLock']);

        // SSH Keys
        Route::get('/ssh-keys', [SshKeyController::class, 'index']);
        Route::post('/ssh-keys', [SshKeyController::class, 'store']);
        Route::delete('/ssh-keys/{sshKey}', [SshKeyController::class, 'destroy']);

        // Firewall
        Route::prefix('servers/{uuid}/firewall')->group(function () {
            Route::get('/', [FirewallController::class, 'index']);
            Route::post('/enable', [FirewallController::class, 'enable']);
            Route::post('/disable', [FirewallController::class, 'disable']);
            Route::post('/rules', [FirewallController::class, 'create']);
            Route::put('/rules/{pos}', [FirewallController::class, 'update']);
            Route::delete('/rules/{pos}', [FirewallController::class, 'destroy']);
            Route::post('/templates', [FirewallController::class, 'applyTemplate']);
        });

        // Guest Agent
        Route::prefix('servers/{uuid}/agent')->group(function () {
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
