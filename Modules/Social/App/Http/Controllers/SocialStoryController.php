<?php

namespace Modules\Social\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Social\App\Services\SocialStoryService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialStoryController extends Controller
{
    public function __construct(
        private readonly SocialStoryService $stories,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->stories->rings($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        return response()->json([
            'story' => $this->stories->store($request->user(), $request->file('file')),
        ], 201);
    }

    public function view(Request $request, int $storyId): JsonResponse
    {
        $this->stories->markViewed($request->user(), $storyId);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $storyId): JsonResponse
    {
        $this->stories->destroy($request->user(), $storyId);

        return response()->json(['ok' => true]);
    }

    public function media(Request $request, int $storyId): StreamedResponse
    {
        return $this->stories->media($request->user(), $storyId);
    }
}
