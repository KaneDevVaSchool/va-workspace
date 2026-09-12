<?php

namespace Modules\Credential\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Credential\App\Http\Requests\StoreCredentialProviderRequest;
use Modules\Credential\App\Http\Requests\UpdateCredentialProviderRequest;
use Modules\Credential\App\Repositories\Contracts\CredentialProviderRepositoryInterface;
use Modules\Credential\App\Services\CredentialProviderService;

class CredentialProviderController extends Controller
{
    public function __construct(
        private readonly CredentialProviderService $service,
        private readonly CredentialProviderRepositoryInterface $providers,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['providers' => $this->service->all()->values()]);
    }

    public function store(StoreCredentialProviderRequest $request): JsonResponse
    {
        $provider = $this->service->create($request->validated());

        return response()->json(['provider' => $provider], 201);
    }

    public function update(UpdateCredentialProviderRequest $request, int $provider): JsonResponse
    {
        $model = $this->providers->find($provider);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy nhà cung cấp.'], 404);
        }

        $updated = $this->service->update($model, $request->validated());

        return response()->json(['provider' => $updated]);
    }

    public function destroy(int $provider): JsonResponse
    {
        $model = $this->providers->find($provider);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy nhà cung cấp.'], 404);
        }

        try {
            $this->service->delete($model);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã xoá nhà cung cấp.']);
    }
}
