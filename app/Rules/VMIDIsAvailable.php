<?php

namespace App\Rules;

use App\Models\Node;
use App\Repositories\Proxmox\Node\ProxmoxAllocationRepository;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * VMIDIsAvailable - Validates VMID is available on node
 */
class VMIDIsAvailable implements ValidationRule
{
    public function __construct(
        protected Node $node
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $allocationRepo = new ProxmoxAllocationRepository($this->node);
            $isUsed = $allocationRepo->isVmidInUse((int) $value);

            if ($isUsed) {
                $fail("VMID {$value} is already in use on this node.");
            }
        } catch (\Exception $e) {
            $fail("Unable to verify VMID availability: {$e->getMessage()}");
        }
    }
}
