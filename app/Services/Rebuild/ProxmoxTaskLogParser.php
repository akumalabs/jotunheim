<?php

namespace App\Services\Rebuild;

use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

class ProxmoxTaskLogParser
{
    public function parseCloneProgress(array $logs): ?array
    {
        $logCount = count($logs);
        Log::debug("ProxmoxTaskLogParser: Processing {$logCount} log entries.");
        
        $logs = array_reverse($logs);
        
        foreach ($logs as $index => $line) {
            $lineData = $line['t'] ?? '';
            
            // Explicit check for completion
            if (str_contains($lineData, '100% complete') || str_contains($lineData, 'clone finished')) {
                 Log::debug("ProxmoxTaskLogParser: Found completion marker at line {$index}");
                 return [
                    'current_bytes' => 0,
                    'total_bytes' => 0,
                    'progress_percent' => 100,
                    'current_formatted' => '100%',
                    'total_formatted' => '100%',
                ];
            }
            
            if (preg_match('/transferred\s+([\d.]+)\s+([A-Za-z]+)\s+of\s+([\d.]+)\s+([A-Za-z]+)(?:\s*\(([\d.]+)%\))?/i', $lineData, $matches)) {
                $current = $this->convertToBytes($matches[1], $matches[2]);
                $total = $this->convertToBytes($matches[3], $matches[4]);
                $percentage = min(($current / $total) * 100, 99.9);
                
                Log::debug("ProxmoxTaskLogParser: Matched progress at line {$index}. Calculated: {$percentage}% ({$matches[0]})");
                
                return [
                    'current_bytes' => $current,
                    'total_bytes' => $total,
                    'progress_percent' => $percentage,
                    'current_formatted' => $this->formatBytes($current),
                    'total_formatted' => $this->formatBytes($total),
                ];
            }

            // Fallback for: "transferring disk data... 35%"
            if (preg_match('/transferring\s+disk\s+data\.{3}\s+(\d+)%/i', $lineData, $matches)) {
                 $percentage = (float) $matches[1];
                 Log::debug("ProxmoxTaskLogParser: Matched simple progress at line {$index}. Value: {$percentage}%");
                 return [
                    'current_bytes' => 0,
                    'total_bytes' => 0,
                    'progress_percent' => $percentage,
                    'current_formatted' => $percentage . '%',
                    'total_formatted' => '100%',
                ];
            }
        }
        
        Log::debug("ProxmoxTaskLogParser: No progress pattern found in logs.");
        return null;
    }

    private function convertToBytes(float $value, string $unit): int
    {
        return match(strtoupper($unit)) {
            'B' => (int) $value,
            'KB', 'KIB' => (int) ($value * 1024),
            'MB', 'MIB' => (int) ($value * 1024 * 1024),
            'GB', 'GIB' => (int) ($value * 1024 * 1024 * 1024),
            'TB', 'TIB' => (int) ($value * 1024 * 1024 * 1024 * 1024),
            default => (int) $value,
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes, 1024) ?: 0);
        $value = $bytes / pow(1024, $pow);
        
        return round($value, 2) . ' ' . ($units[$pow] ?? 'B');
    }


}
