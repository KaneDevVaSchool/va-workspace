<?php

namespace Modules\Example\App\Repositories;

use Modules\Example\App\Models\Example;
use Modules\Example\App\Repositories\Contracts\ExampleRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp. Service KHÔNG được
 * query Model thẳng — luôn đi qua Repository.
 */
class ExampleRepository implements ExampleRepositoryInterface
{
    public function all(): iterable
    {
        return Example::query()->get();
    }

    public function find(int $id)
    {
        return Example::query()->findOrFail($id);
    }

    public function create(array $data)
    {
        return Example::query()->create($data);
    }

    public function update(int $id, array $data)
    {
        $model = $this->find($id);
        $model->update($data);

        return $model;
    }

    public function delete(int $id): bool
    {
        return (bool) $this->find($id)->delete();
    }
}
