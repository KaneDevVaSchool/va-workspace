<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Hrm\Services\HrmWebhookDispatcher;

/**
 * Nhận webhook đẩy từ VA-HRM (employee.updated/transferred/terminated,
 * org_unit.changed). Chữ ký đã được xác thực bởi middleware
 * hrm.webhook.signature trước khi tới đây. Trả 2xx nhanh — xử lý nghiệp vụ
 * nằm ở Job (queue), vì HRM retry (backoff 60s/300s/900s/3600s, tối đa 5
 * lần) nếu response không 2xx.
 */
class HrmWebhookController extends Controller
{
    public function handle(Request $request, HrmWebhookDispatcher $dispatcher): JsonResponse
    {
        $dispatcher->dispatch(
            event: $request->header('X-VA-HRM-Event'),
            deliveryId: $request->header('X-VA-HRM-Delivery-Id'),
            payload: (array) $request->json('data', []),
        );

        return response()->json(['received' => true]);
    }
}
