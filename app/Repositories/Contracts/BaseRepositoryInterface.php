<?php

namespace App\Repositories\Contracts;

interface BaseRepositoryInterface
{
    public function all(array $filters = []);

    public function find(int|string $id);

    public function create(array $data);

    public function update(int|string $id, array $data);

    public function delete(int|string $id): bool;
}
