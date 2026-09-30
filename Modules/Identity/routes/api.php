<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\HrmWebhookController;

/*
|--------------------------------------------------------------------------
| Identity Module — API Routes
|--------------------------------------------------------------------------
| Endpoint SPA đã chuyển sang web (GET /me, POST/DELETE /view-as) để dùng
| cùng session với CSRF/logout. Middleware 'api' (stateless, không session/
| CSRF) — đúng cho webhook VA-HRM (xác thực bằng chữ ký HMAC, không session).
*/

Route::middleware(['hrm.webhook.signature'])
    ->post('/hrm/webhook', [HrmWebhookController::class, 'handle'])
    ->name('hrm.webhook');
