<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Hostname - Validates a hostname (RFC 1123)
 */
class Hostname implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // RFC 1123 hostname validation
        // - 1-63 characters per label, 1-253 total
        // - starts with alphanumeric
        // - can contain alphanumeric and hyphens
        // - no consecutive hyphens at start
        $pattern = '/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/';

        if (strlen($value) > 63 || strlen($value) < 1) {
            $fail('The :attribute must be between 1 and 63 characters.');

            return;
        }

        if (! preg_match($pattern, $value)) {
            $fail('The :attribute must be a valid hostname (alphanumeric and hyphens only, must start and end with alphanumeric).');
        }
    }
}
