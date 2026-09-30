<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Identity\App\Exceptions\AccountNotUsable;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Exceptions\HrmTokenInvalid;
use Modules\Identity\App\Hrm\Services\HrmJwtVerifier;
use Modules\Identity\App\Hrm\Services\HrmSsoService;
use Modules\Identity\App\Services\ActivityLogService;

/**
 * SSO qua VA-HRM — thay thế GoogleAuthController. Controller mỏng: chỉ
 * điều phối request/redirect, business logic nằm ở HrmSsoService.
 */
class HrmSsoController extends Controller
{
    private const STATE_SESSION_KEY = 'hrm_sso.state';

    public function __construct(
        private readonly HrmJwtVerifier $jwtVerifier,
        private readonly HrmSsoService $ssoService,
        private readonly ActivityLogService $activityLogs,
    ) {}

    /** Chuyển hướng sang màn hình authorize của VA-HRM. */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->hrmConfigured()) {
            return $this->failLogin('Đăng nhập VA-HRM chưa được cấu hình trên máy chủ.');
        }

        if ($request->user()) {
            return redirect()->to($this->frontendUrl('/'));
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_SESSION_KEY, $state);
        $request->session()->put(
            'login.redirect',
            $this->sanitizeRedirect($request->query('redirect')),
        );

        $query = http_build_query([
            'client_id' => config('services.hrm.sso_client_id'),
            'redirect_uri' => config('services.hrm.sso_callback_url'),
            'state' => $state,
        ]);

        $base = rtrim((string) config('services.hrm.api_base_url'), '/');

        return redirect()->away("{$base}/sso/authorize?{$query}");
    }

    /** Nhận callback từ VA-HRM, xác thực JWT và tạo session. */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->hrmConfigured()) {
            return $this->failLogin('Đăng nhập VA-HRM chưa được cấu hình.');
        }

        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $state = $request->query('state');

        if (! is_string($expectedState) || $state !== $expectedState) {
            return $this->failLogin('Phiên đăng nhập đã hết hạn. Vui lòng thử lại.');
        }

        $token = $request->query('token');
        if (! is_string($token) || $token === '') {
            return $this->failLogin('Đăng nhập bị huỷ hoặc thiếu token.');
        }

        try {
            $claims = $this->jwtVerifier->verify($token);
        } catch (HrmTokenInvalid) {
            return $this->failLogin('Phiên đăng nhập không hợp lệ. Vui lòng thử lại.');
        }

        if (($claims['aud'] ?? null) !== config('services.hrm.sso_client_id')) {
            return $this->failLogin('Phiên đăng nhập không hợp lệ. Vui lòng thử lại.');
        }

        try {
            $user = $this->ssoService->authenticate($claims);
        } catch (AccountNotUsable) {
            return $this->failLogin('Tài khoản của bạn hiện không thể đăng nhập. Vui lòng liên hệ quản trị viên.');
        } catch (HrmApiUnavailable) {
            return $this->failLogin('Không thể kết nối hệ thống VA-HRM. Vui lòng thử lại sau.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $this->activityLogs->record('auth.login', 'Đăng nhập vào hệ thống (VA-HRM SSO)', $user);

        $redirect = $request->session()->pull('login.redirect');

        return redirect()->to($this->frontendUrl('/auth/callback', [
            'status' => 'ok',
            'redirect' => $redirect,
        ]));
    }

    private function hrmConfigured(): bool
    {
        return filled(config('services.hrm.api_base_url'))
            && filled(config('services.hrm.sso_client_id'));
    }

    private function failLogin(string $message): RedirectResponse
    {
        return redirect()->to($this->frontendUrl('/login', ['error' => $message]));
    }

    /** @param array<string, string|null> $query */
    private function frontendUrl(string $path, array $query = []): string
    {
        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');
        $base = rtrim(config('app.url'), '/').$path;

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }

    /** Chỉ cho phép quay về path nội bộ (SPA), chặn open-redirect. */
    private function sanitizeRedirect(mixed $redirect): ?string
    {
        if (! is_string($redirect) || $redirect === '' || ! str_starts_with($redirect, '/')) {
            return null;
        }

        if (str_starts_with($redirect, '//')) {
            return null;
        }

        return $redirect;
    }
}
