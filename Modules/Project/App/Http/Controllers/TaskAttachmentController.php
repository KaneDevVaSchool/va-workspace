<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Project\App\Http\Requests\StoreTaskAttachmentRequest;
use Modules\Project\App\Http\Requests\UpdateTaskAttachmentRequest;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskAttachment;
use Modules\Project\App\Services\TaskAttachmentService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 */
class TaskAttachmentController extends Controller
{
    public function __construct(private readonly TaskAttachmentService $service) {}

    /** GET /api/project/tasks/{task}/attachments */
    public function index(Task $task)
    {
        $attachments = $this->service->listForTask($task);

        return response()->json([
            'attachments' => $attachments->map(fn (TaskAttachment $a) => $this->service->present($a))->values(),
        ]);
    }

    /** POST /api/project/tasks/{task}/attachments */
    public function store(StoreTaskAttachmentRequest $request, Task $task)
    {
        $attachment = $this->service->upload($task, $request->file('file'), $request->user());

        return response()->json(['attachment' => $this->service->present($attachment)], 201);
    }

    /** PUT /api/project/tasks/attachments/{attachment} */
    public function update(UpdateTaskAttachmentRequest $request, int $attachment)
    {
        $result = $this->service->rename($attachment, $request->validated()['file_name']);
        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 404);
        }

        return response()->json(['attachment' => $this->service->present($result)]);
    }

    /** POST /api/project/tasks/attachments/{attachment}/replace */
    public function replace(StoreTaskAttachmentRequest $request, int $attachment)
    {
        $result = $this->service->replace($attachment, $request->file('file'), $request->user());
        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 404);
        }

        return response()->json(['attachment' => $this->service->present($result)]);
    }

    /** DELETE /api/project/tasks/attachments/{attachment} */
    public function destroy(int $attachment)
    {
        $error = $this->service->delete($attachment);
        if ($error !== null) {
            return response()->json(['message' => $error['error']], 404);
        }

        return response()->json(['message' => 'Đã xoá tệp đính kèm.']);
    }
}
