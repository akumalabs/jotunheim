<?php

namespace App\Services\Proxmox;

use Exception;

class ProxmoxApiException extends Exception
{
    protected mixed $errors;

    public function __construct(mixed $errors = null, int $code = 500, ?Exception $previous = null)
    {
        $this->errors = $errors;

        $message = is_string($errors) ? $errors : json_encode($errors);
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the raw errors from the Proxmox API.
     */
    public function getErrors(): mixed
    {
        return $this->errors;
    }
}
