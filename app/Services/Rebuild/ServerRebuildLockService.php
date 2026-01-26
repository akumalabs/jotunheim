<?php

namespace App\Services\Rebuild;

use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServerRebuildLockService
{
    public function acquire(Server $server): bool
    {
        try {
            DB::table('server_rebuild_locks')->insert([
                'server_id' => $server->id,
                'locked_at' => now(),
                'expires_at' => now()->addHours(2),
            ]);
            
            Log::info("[RebuildLock] Acquired lock for server {$server->id}");
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint violation means already locked
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                Log::warning("[RebuildLock] Server {$server->id} already locked");
                return false;
            }
            throw $e;
        }
    }

    public function release(Server $server): void
    {
        $deleted = DB::table('server_rebuild_locks')
            ->where('server_id', $server->id)
            ->delete();
            
        if ($deleted > 0) {
            Log::info("[RebuildLock] Released lock for server {$server->id}");
        }
    }

    public function isLocked(Server $server): bool
    {
        return DB::table('server_rebuild_locks')
            ->where('server_id', $server->id)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
