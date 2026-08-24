<?php

namespace Modules\Example\App\Repositories\Contracts;

/**
 * Contract cho tầng Repository — Service chỉ phụ thuộc interface này,
 * không phụ thuộc trực tiếp Eloquent, để dễ test/mock và đổi nguồn dữ liệu.
 */
interface ExampleRepositoryInterface
{
    public function all(): iterable;

    public function find(int $id);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id): bool;
}
