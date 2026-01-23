<?php

namespace App\Rules;

use App\Models\Node;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * HasSufficientDiskSpace - Validates node has enough disk space for server
 */
class HasSufficientDiskSpace implements ValidationRule
{
    public function __construct(
        protected Node $node,
        protected int $requiredDisk
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $usedDisk = $this->node->servers()->sum('disk');
        $availableDisk = $this->node->disk - $usedDisk;

        if ($this->requiredDisk > $availableDisk) {
            $fail("Insufficient disk space on node. Required: {$this->requiredDisk}GB, Available: {$availableDisk}GB");
        }
    }
}
