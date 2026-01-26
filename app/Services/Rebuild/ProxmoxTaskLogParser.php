<?php

namespace App\Services\Rebuild;

use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

class ProxmoxTaskLogParser
{
    public function parseCloneProgress(array $logs): ?array
    {
        $logs = array_reverse($logs);
        
        foreach ($logs as $line) {
            $lineData = $line['t'] ?? '';
            
            if (preg_match('/transferred\s+([\d.]+)\s+([A-Za-z]+)\s+of\s+([\d.]+)\s+([A-Za-z]+)(?:\s*\(([\d.]+)%\))?/i', $lineData, $matches)) {
                $current = $this->convertToBytes($matches[1], $matches[2]);
                $total = $this->transferToBytes($matches[3], $matches[4]);
                $percentage = min(($current / $total) * 100, 99.9);
                
                return [
                    'current_bytes' => $current,
                    'total_bytes' => $total,
                    'progress_percent' => $percentage,
                    'current_formatted' => $this->formatBytes($current),
                    'total_formatted' => $this->formatBytes($total),
                ];
            }
        }
        
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
