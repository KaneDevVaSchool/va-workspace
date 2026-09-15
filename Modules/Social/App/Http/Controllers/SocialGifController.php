<?php

namespace Modules\Social\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Social\App\Http\Requests\DownloadSocialGifRequest;
use Modules\Social\App\Http\Requests\SearchSocialGifRequest;
use Modules\Social\App\Services\SocialGifService;

class SocialGifController extends Controller
{
    public function __construct(private readonly SocialGifService $service) {}

    public function search(SearchSocialGifRequest $request): JsonResponse
    {
        $result = $this->service->search(
            (string) $request->validated('q', ''),
            (int) $request->validated('page', 1),
            (string) $request->validated('kind', 'gif'),
        );

        return response()->json($result);
    }

    public function download(DownloadSocialGifRequest $request): JsonResponse
    {
        return response()->json($this->service->download($request->validated('url')));
    }
}
