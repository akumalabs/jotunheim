<?php

namespace App\Services\Isos;

use App\Jobs\Node\MonitorIsoDownloadJob;
use App\Models\Iso;
use App\Models\Node;
use App\Repositories\Proxmox\Node\ProxmoxStorageRepository;

class IsoService
{
    public function __construct(
        protected ProxmoxStorageRepository $storageRepository
    ) {}

    /**
     * Download an ISO from URL to node storage.
     */
    public function download(Node $node, string $url, string $filename): Iso
    {
        // Create ISO record
        $iso = Iso::create([
            'node_id' => $node->id,
            'name' => $filename,
            'file_name' => $filename,
            'size' => 0,
            'is_downloading' => true,
        ]);

        // Start download on Proxmox
        $taskId = $this->storageRepository->downloadIso($node, $url, $filename);

        $iso->update(['task_id' => $taskId]);

        // Dispatch job to monitor download progress
        MonitorIsoDownloadJob::dispatch($iso, $taskId);

        return $iso;
    }

    /**
     * Delete an ISO from node storage.
     */
    public function delete(Iso $iso): void
    {
        $node = $iso->node;

        // Delete from Proxmox storage
        $this->storageRepository->deleteIso($node, $iso->file_name);

        // Delete record
        $iso->delete();
    }

    /**
     * List ISOs available on a node.
     */
    public function list(Node $node): array
    {
        return $this->storageRepository->listIsos($node);
    }

    /**
     * Sync ISOs from node storage to database.
     */
    public function sync(Node $node): void
    {
        $proxmoxIsos = $this->storageRepository->listIsos($node);
        $existingIsos = $node->isos()->pluck('file_name')->toArray();

        foreach ($proxmoxIsos as $isoData) {
            $filename = $isoData['volid'] ?? $isoData['name'];

            if (! in_array($filename, $existingIsos)) {
                Iso::create([
                    'node_id' => $node->id,
                    'name' => pathinfo($filename, PATHINFO_FILENAME),
                    'file_name' => $filename,
                    'size' => $isoData['size'] ?? 0,
                    'is_downloading' => false,
                ]);
            }
        }
    }
}
