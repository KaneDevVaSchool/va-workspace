<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Exceptions\ShortcutPathTaken;
use Modules\Identity\App\Http\Requests\StoreShortcutRequest;
use Modules\Identity\App\Http\Requests\UpdateShortcutRequest;
use Modules\Identity\App\Repositories\Contracts\UserShortcutRepositoryInterface;
use Modules\Identity\App\Services\ShortcutService;

class ShortcutController extends Controller
{
    public function __construct(
        private readonly ShortcutService $shortcuts,
        private readonly UserShortcutRepositoryInterface $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'shortcuts' => $this->shortcuts->listFor((int) $request->user()->id),
        ]);
    }

    public function store(StoreShortcutRequest $request): JsonResponse
    {
        try {
            $shortcut = $this->shortcuts->create(
                (int) $request->user()->id,
                $request->validated(),
            );
        } catch (ShortcutPathTaken $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Đã thêm vào lối tắt.',
            'shortcut' => $this->shortcuts->present($shortcut),
        ], 201);
    }

    public function update(UpdateShortcutRequest $request, int $shortcut): JsonResponse
    {
        $model = $this->repository->findForUser((int) $request->user()->id, $shortcut);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy lối tắt.'], 404);
        }

        $model = $this->shortcuts->update($model, $request->validated());

        return response()->json([
            'message' => 'Đã cập nhật lối tắt.',
            'shortcut' => $this->shortcuts->present($model),
        ]);
    }

    public function toggleFavorite(Request $request, int $shortcut): JsonResponse
    {
        $model = $this->repository->findForUser((int) $request->user()->id, $shortcut);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy lối tắt.'], 404);
        }

        $model = $this->shortcuts->toggleFavorite($model);

        return response()->json([
            'shortcut' => $this->shortcuts->present($model),
        ]);
    }

    public function destroy(Request $request, int $shortcut): JsonResponse
    {
        $model = $this->repository->findForUser((int) $request->user()->id, $shortcut);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy lối tắt.'], 404);
        }

        $this->shortcuts->delete($model);

        return response()->json(['message' => 'Đã xoá lối tắt.']);
    }
}
