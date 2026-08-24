<?php

namespace Modules\Example\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Example\App\Services\ExampleService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 * Không chứa business logic hay truy vấn DB.
 */
class ExampleController extends Controller
{
    public function __construct(
        protected ExampleService $service
    ) {
    }

    public function index()
    {
        return response()->json($this->service->list());
    }
}
