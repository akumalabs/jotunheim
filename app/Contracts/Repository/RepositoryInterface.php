<?php

namespace App\Contracts\Repository;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RepositoryInterface
{
    public function model(): string;

    public function find(int $id);

    public function create(array $fields);

    public function update($id, array $fields);

    public function delete(int $id);

    public function all(): Collection;

    public function paginated(int $perPage): LengthAwarePaginator;

    public function findBy(array $criteria): Collection;

    public function exists(int $id): bool;
}
