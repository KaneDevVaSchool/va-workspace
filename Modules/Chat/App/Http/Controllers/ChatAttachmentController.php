<?php

namespace Modules\Chat\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Chat\App\Models\MessageAttachment;
use Modules\Chat\App\Services\ChatService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public function __construct(
        private readonly ChatService $service,
    ) {}

    public function show(Request $request, int $attachmentId): StreamedResponse
    {
        $attachment = MessageAttachment::query()->with('message')->find($attachmentId);
        if (! $attachment || ! $attachment->message) {
            abort(404);
        }

        if (! $this->service->isMember($request->user(), (int) $attachment->message->conversation_id)) {
            abort(403);
        }

        $disk = $attachment->disk ?: 'public';
        if (! Storage::disk($disk)->exists($attachment->file_path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($attachment->file_path, $attachment->file_name);
    }
}
