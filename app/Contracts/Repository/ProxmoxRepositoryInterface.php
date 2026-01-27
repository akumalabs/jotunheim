<?php

namespace App\Contracts\Repository;

interface ProxmoxRepositoryInterface
{
    public function getNode(): string;

    public function getApiUrl(): string;

    public function executeRequest(string $method, string $path, array $data = []): array|string;

    public function get(string $path, array $query = []): array|string;

    public function post(string $path, array $data = []): array|string;

    public function put(string $path, array $data = []): array|string;

    public function delete(string $path, array $data = []): array|string;
}
