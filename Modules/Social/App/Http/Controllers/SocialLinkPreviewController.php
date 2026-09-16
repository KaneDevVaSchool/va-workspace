<?php

namespace Modules\Social\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Social\App\Services\SocialLinkPreviewService;

class SocialLinkPreviewController
{
    public function __construct(
        private readonly SocialLinkPreviewService $linkPreviews,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url'],
        ]);

        return response()->json([
            'preview' => $this->linkPreviews->previewForUrl($data['url']),
        ]);
    }
}
