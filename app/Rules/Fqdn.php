<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fqdn - Validates a fully qualified domain name
 */
class Fqdn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Allow hostname.domain.tld format
        $pattern = '/^(?!:\/\/)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/';

        if (! preg_match($pattern, $value)) {
            // Also allow IP addresses
            if (! filter_var($value, FILTER_VALIDATE_IP)) {
                $fail('The :attribute must be a valid fully qualified domain name or IP address.');
            }
        }
    }
}
