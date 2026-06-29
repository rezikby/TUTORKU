<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function all(array $filters = [])
    {
        $query = $this->model->newQuery();

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $query->where($key, $value);
        }

        return $query->get();
    }

    public function find(int|string $id)
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(int|string $id, array $data)
    {
        $model = $this->find($id);
        $model->fill($data);
        $model->save();

        return $model;
    }

    public function delete(int|string $id): bool
    {
        $model = $this->find($id);

        return (bool) $model->delete();
    }
}
