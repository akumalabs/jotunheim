# PVE Resize Timeout Audit Report

## Executive Summary
Critical timeout issues identified in the codebase causing PVE resize operations to fail. The resize functionality runs synchronously without proper timeout configuration, leading to failures during disk resize operations.

---

## Critical Issues

### 1. ⚠️ Synchronous Resize Operations (CRITICAL)
**Location:** `app/Services/Servers/ServerResizeService.php:20-74`

**Problem:**
- User-initiated resize via `ServerResizeController::resize()` calls `ServerResizeService` directly
- No job dispatch - operation runs synchronously in HTTP request
- Disk resize can take minutes to complete depending on storage speed and disk size
- HTTP request times out before resize completes

**Evidence:**
```php
// app/Http/Controllers/Api/Client/ServerResizeController.php:41
$this->resizeService->resize($server, $validated); // Synchronous call!
```

**Impact:** Users always get timeout errors when resizing disks

**Recommendation:** Create a queued job for resize operations

---

### 2. ⚠️ PHP Timeout Configuration Conflicts (CRITICAL)

**Problem A: Socket Timeout Too Short**
- PHP `default_socket_timeout`: **60 seconds** (system default)
- ProxmoxApiClient HTTP timeout: **600 seconds** (line 37)
- PHP will kill network connection after 60s regardless of HTTP client setting

**Problem B: Max Execution Time Too Short**
- PHP `max_execution_time`: **30 seconds** (php.ini)
- Any operation exceeding 30s will be killed by PHP
- Disk resize operations typically exceed 30s

**Evidence:**
```bash
# System settings
max_execution_time = 30
default_socket_timeout = 60
```

**Location:** 
- `/etc/php/8.2/fpm/php.ini`
- `/etc/php/8.3/fpm/php.ini`

**Recommendation:**
- Increase `max_execution_time` to 600s or disable (0)
- Increase `default_socket_timeout` to 600s
- Or better: use queued jobs to avoid timeout issues entirely

---

### 3. ⚠️ No Task Completion Tracking (HIGH)

**Problem:**
- `ServerResizeService::resize()` calls `$repo->resizeDisk()` which sends resize request to PVE
- Returns immediately without waiting for task completion
- PVE returns a task UPID (task ID) but service ignores it
- No verification that resize actually completed successfully

**Evidence:**
```php
// app/Services/Servers/ServerResizeService.php:50
$repo->resizeDisk('scsi0', "{$newDiskSize}G"); 
// Returns immediately, no task waiting!
```

**Comparison:** 
- CreateServerJob (line 125-130): Waits for unlock after resize
- ConfigureVmJob (line 119-146): Has retry logic with task waiting
- ServerResizeService: No task waiting at all

**Impact:**
- Server status set to "installing" but resize may not have started
- No error handling if resize fails
- User sees success message but resize didn't complete

**Recommendation:**
- Wait for task completion using `$client->waitForTask()`
- Handle task failure appropriately
- Verify resize completed before updating server status

---

### 4. ⚠️ Inconsistent Resize Implementation (MEDIUM)

**Problem:**
Three different implementations for resize:

1. **CreateServerJob** (queued, with task waiting)
2. **ConfigureVmJob** (queued, with retry logic and task waiting)
3. **ServerResizeService** (synchronous, no task waiting)

**Evidence:**
```php
// CreateServerJob: Line 125-130
$configRepo->resizeDisk('scsi0', $this->server->disk); 
if (!$serverRepo->waitUntilUnlocked(60, 2)) {
    throw new \Exception("VM locked timeout after resize.");
}

// ConfigureVmJob: Line 107-141
// Has full retry loop with exponential backoff
while ($attempts < $maxResizeAttempts) {
    try {
        $configRepo->resizeDisk('scsi0', $this->server->disk);
        break; // Success
    } catch (\Exception $e) {
        // Retry logic...
    }
}

// ServerResizeService: Line 50
$repo->resizeDisk('scsi0', "{$newDiskSize}G");
// No waiting, no retry, no task tracking
```

**Recommendation:** Unify all resize operations to use queued jobs with proper error handling

---

### 5. ⚠️ Admin Controller Also Has Synchronous Resize (HIGH)

**Location:** `app/Http/Controllers/Api/Admin/ServerController.php:937-945`

**Problem:**
- Admin update endpoint also calls resize synchronously
- Same timeout issues as client-side resize
- No job dispatch

**Evidence:**
```php
// Line 941-44
$client->put("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/resize", [
    'disk' => 'scsi0',
    'size' => "+{$increaseGB}G"
]);
// Synchronous, no task waiting
```

**Impact:** Admin updates also timeout

**Recommendation:** Same fix as ServerResizeService - dispatch job instead

---

## Detailed Code Analysis

### HTTP Timeout Configuration

**File:** `app/Services/Proxmox/ProxmoxApiClient.php:25-38`

```php
protected function createClient(): PendingRequest
{
    $verify = env('PROXMOX_VERIFY_SSL', true);

    return Http::baseUrl($this->node->getApiUrl())
        ->asForm() // Proxmox expects form-data, not JSON
        ->withHeaders([
            'Authorization' => "PVEAPIToken={$this->node->token_id}={$this->node->token_secret}",
        ])
        ->withOptions([
            'verify' => $verify,
        ])
        ->timeout(600); // HTTP timeout set to 600s
}
```

**Issue:** This 600s timeout is overridden by PHP's 60s socket timeout and 30s max execution time.

---

### Resize Method Implementation

**File:** `app/Repositories/Proxmox/Server/ProxmoxConfigRepository.php:76-87`

```php
public function resizeDisk(string $disk, int $bytes): string
{
    // Proxmox expects size in kibibytes for resize
    $kib = (int) round($bytes / 1024);

    $response = $this->client->put($this->vmPath('resize'), [
        'disk' => $disk,
        'size' => "{$kib}K",
    ]);

    return is_string($response) ? $response : ($response['data'] ?? '');
}
```

**Issue:** Returns a task UPID string but calling code doesn't track it.

---

### Wait Until Unlocked Logic

**File:** `app/Repositories/Proxmox/Server/ProxmoxServerRepository.php:66-76`

```php
public function waitUntilUnlocked(int $maxAttempts = 60, int $interval = 2): bool
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        if (! $this->isLocked()) {
            return true;
        }
        sleep($interval);
    }

    return false;
}
```

**Good:** This method is used properly in CreateServerJob and ConfigureVmJob
**Bad:** Not used at all in ServerResizeService

---

## Recommended Solutions

### Solution 1: Create Resize Job (RECOMMENDED)

Create a new queued job to handle resize operations:

```php
// app/Jobs/Server/ResizeServerJob.php
<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResizeServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        protected Server $server,
        protected array $options
    ) {}

    public function handle(): void
    {
        $client = new ProxmoxApiClient($this->server->node);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($this->server);
        $serverRepo = (new ProxmoxServerRepository($client))->setServer($this->server);

        try {
            if (isset($this->options['disk'])) {
                $newDiskSize = ceil($this->options['disk'] / 1073741824);
                
                // Wait for unlock before resize
                if (!$serverRepo->waitUntilUnlocked(60, 2)) {
                    throw new \Exception("VM locked timeout before resize");
                }

                // Resize disk
                $taskUpid = $configRepo->resizeDisk('scsi0', $this->options['disk']);
                
                // Wait for task completion
                if (is_string($taskUpid) && str_contains($taskUpid, 'UPID:')) {
                    $client->waitForTask($taskUpid, 600);
                }

                // Wait for unlock after resize
                if (!$serverRepo->waitUntilUnlocked(120, 2)) {
                    throw new \Exception("VM locked timeout after resize");
                }

                $this->server->update([
                    'disk' => $this->options['disk'],
                    'status' => 'running',
                ]);
            }

            if (isset($this->options['cpu'])) {
                $configRepo->setCpu($this->options['cpu'], 1);
                $this->server->update(['cpu' => $this->options['cpu']]);
            }

            if (isset($this->options['memory'])) {
                $configRepo->setMemory($this->options['memory']);
                $this->server->update(['memory' => $this->options['memory']]);
            }

            Log::info("Resize completed for server {$this->server->uuid}");
        } catch (\Exception $e) {
            Log::error("Resize failed for server {$this->server->uuid}: " . $e->getMessage());
            $this->server->update(['status' => 'error']);
            throw $e;
        }
    }
}
```

Update controller to dispatch job:

```php
// app/Http/Controllers/Api/Client/ServerResizeController.php
public function resize(Request $request, string $uuid): JsonResponse
{
    $server = $request->user()
        ->servers()
        ->where('uuid', $uuid)
        ->firstOrFail();

    if ($server->is_suspended) {
        return response()->json([
            'message' => 'Cannot resize a suspended server',
        ], 403);
    }

    $validated = $request->validate([
        'cpu' => ['sometimes', 'integer', 'min:1', 'max:32'],
        'memory' => ['sometimes', 'integer', 'min:512', 'max:1024*1024'],
        'disk' => ['sometimes', 'integer', 'min:10', 'max:10240'],
    ]);

    // Dispatch job instead of running synchronously
    \App\Jobs\Server\ResizeServerJob::dispatch($server, $validated);

    return response()->json([
        'message' => 'Server resize initiated',
        'status' => 'processing',
    ]);
}
```

---

### Solution 2: Increase PHP Timeouts (QUICK FIX)

**Not recommended as a long-term solution** but can help temporarily:

```ini
# /etc/php/8.2/fpm/php.ini
max_execution_time = 600
default_socket_timeout = 600

# /etc/php/8.3/fpm/php.ini
max_execution_time = 600
default_socket_timeout = 600
```

Then restart PHP-FPM:
```bash
systemctl restart php8.2-fpm
systemctl restart php8.3-fpm
```

---

### Solution 3: Add Task Waiting to Current Service (TEMPORARY)

If you can't create a job right now, at least add task waiting:

```php
// app/Services/Servers/ServerResizeService.php
public function resize(Server $server, array $options): void
{
    try {
        Log::info("Starting resize for server {$server->uuid}");

        $repo = (new ProxmoxServerRepository($this->client))->setServer($server);
        $configRepo = (new ProxmoxConfigRepository($this->client))->setServer($server);

        if (isset($options['disk']) && $options['disk'] >= 10 && $options['disk'] <= 10240) {
            $newDiskSize = ceil($options['disk'] / 1073741824);
            Log::info("Resizing disk to {$options['disk']} bytes ({$newDiskSize}G)");

            // Wait for unlock before resize
            if (!$repo->waitUntilUnlocked(60, 2)) {
                throw new \Exception("VM locked timeout before resize");
            }

            $taskUpid = $repo->resizeDisk('scsi0', "{$newDiskSize}G");
            
            // Wait for task completion
            if (is_string($taskUpid) && str_contains($taskUpid, 'UPID:')) {
                $this->client->waitForTask($taskUpid, 600);
                Log::info("Resize task completed: {$taskUpid}");
            }

            // Wait for unlock after resize
            if (!$repo->waitUntilUnlocked(120, 2)) {
                throw new \Exception("VM locked timeout after resize");
            }

            $server->update(['disk' => $options['disk'], 'status' => 'running']);
        }

        // ... rest of resize logic

    } catch (ProxmoxApiException $e) {
        Log::error("Failed to resize server {$server->uuid}: ".$e->getMessage());
        throw $e;
    }
}
```

---

## Testing Recommendations

1. Test resize with small disks (10G) - should complete quickly
2. Test resize with large disks (100G+) - this will reveal timeout issues
3. Test resize on slow storage (e.g., HDD instead of SSD)
4. Test concurrent resize operations
5. Verify task UPIDs are being tracked and waited for
6. Verify server status updates correctly after resize completion

---

## Priority Actions

1. **IMMEDIATE:** Create ResizeServerJob and update controllers to use it
2. **HIGH:** Increase PHP timeouts to 600s (as temporary fix)
3. **MEDIUM:** Unify all resize operations to use queued jobs
4. **LOW:** Add better error handling and logging

---

## Files Requiring Changes

- `app/Jobs/Server/ResizeServerJob.php` (NEW)
- `app/Services/Servers/ServerResizeService.php` (REFACTOR or REMOVE)
- `app/Http/Controllers/Api/Client/ServerResizeController.php` (UPDATE)
- `app/Http/Controllers/Api/Admin/ServerController.php` (UPDATE)
- `/etc/php/8.2/fpm/php.ini` (CONFIG)
- `/etc/php/8.3/fpm/php.ini` (CONFIG)
