<?php

namespace App\Services\Network;

use App\Models\RdnsRecord;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

class RdnsService
{
    public function getServerRdnsRecords(Server $server): array
    {
        return RdnsRecord::where('server_id', $server->id)->get()->toArray();
    }

    public function createRdnsRecord(Server $server, string $ipAddress, string $ptrRecord, string $mode = 'manual'): RdnsRecord
    {
        $record = RdnsRecord::create([
            'server_id' => $server->id,
            'ip_address' => $ipAddress,
            'ptr_record' => $ptrRecord,
            'mode' => $mode,
            'verified' => false,
        ]);

        Log::info("RDNS record created for server {$server->uuid}", [
            'ip_address' => $ipAddress,
            'ptr_record' => $ptrRecord,
            'mode' => $mode,
        ]);

        return $record;
    }

    public function updateRdnsRecord(RdnsRecord $record, array $data): RdnsRecord
    {
        $record->update($data);

        Log::info("RDNS record {$record->id} updated", $data);

        return $record->fresh();
    }

    public function deleteRdnsRecord(RdnsRecord $record): void
    {
        $record->delete();

        Log::info("RDNS record {$record->id} deleted");
    }

    public function verifyRdnsRecord(RdnsRecord $record): RdnsRecord
    {
        $record->update(['verified' => true]);

        Log::info("RDNS record {$record->id} verified");

        return $record->fresh();
    }
}
