<?php

namespace Modules\Example\App\Services;

use Modules\Example\App\Repositories\Contracts\ExampleRepositoryInterface;

/**
 * Business logic của module. Controller chỉ gọi Service, không bao giờ
 * gọi Repository hay Model trực tiếp.
 */
class ExampleService
{
    public function __construct(
        protected ExampleRepositoryInterface $repository
    ) {
    }

    public function list(): iterable
    {
        return $this->repository->all();
    }

    public function create(array $data)
    {
        // Validate/transform nghiệp vụ ở đây trước khi lưu.
        return $this->repository->create($data);
    }
}
