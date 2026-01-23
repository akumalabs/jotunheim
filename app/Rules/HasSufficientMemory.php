<?php

namespace App\Rules;

use App\Models\Node;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * HasSufficientMemory - Validates node has enough memory for server
 */
class HasSufficientMemory implements ValidationRule
{
    public function __construct(
        protected Node $node,
        protected int $requiredMemory
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $usedMemory = $this->node->servers()->sum('memory');
        $availableMemory = $this->node->memory - $usedMemory;

        if ($this->requiredMemory > $availableMemory) {
            $fail("Insufficient memory on node. Required: {$this->requiredMemory}MB, Available: {$availableMemory}MB");
        }
    }
}
