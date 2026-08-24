<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Modules\Identity\App\Exceptions\AccountNotUsable;
use Modules\Identity\App\Exceptions\EmailDomainNotAllowed;
use Modules\Identity\App\Services\GoogleAuthenticator;
use Modules\Identity\App\Services\ViewAsService;

/**
 * Google Workspace SSO — bản rút gọn từ va-hrm (không mobile deep-link,
 * không tunnel host, không break-glass fallback — chưa cần ở giai đoạn này).
 *
 * Controller mỏng: chỉ điều phối request/redirect, business logic nằm ở
 * GoogleAuthenticator (Service).
 */
class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthenticator $authenticator,
    ) {}

    /** Chuyển hướng sang Google consent screen. */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return $this->failLogin('Đăng nhập Google chưa được cấu hình trên máy chủ.');
        }

        // Đã đăng nhập rồi → về thẳng trang chủ (SPA), không chạy lại OAuth.
        if ($request->user()) {
            return redirect()->to($this->frontendUrl('/'));
        }

        $request->session()->put(
            'login.redirect',
            $this->sanitizeRedirect($request->query('redirect')),
        );

        if (config('app.debug')) {
            Log::warning('Google OAuth redirect() — session trước khi sang Google', [
                'session_id' => $request->session()->getId(),
                'has_session_cookie' => $request->hasCookie(config('session.cookie')),
                'is_secure' => $request->isSecure(),
                'scheme_forwarded' => $request->header('X-Forwarded-Proto'),
                'host' => $request->getHost(),
            ]);
        }

        $domains = (array) config('services.google.allowed_domains', []);

        $params = array_filter([
            'hd' => $domains[0] ?? null,
            'prompt' => 'select_account',
        ]);

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with($params)
            ->redirect();
    }

    /** Nhận callback từ Google, xác thực và tạo session. */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return $this->failLogin('Đăng nhập Google chưa được cấu hình.');
        }

        if ($request->has('error')) {
            return $this->failLogin('Bạn đã hủy đăng nhập bằng Google.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            if (config('app.debug')) {
                Log::warning('Google OAuth InvalidStateException', [
                    'message' => $e->getMessage(),
                    'session_id' => $request->session()->getId(),
                    'has_session_cookie' => $request->hasCookie(config('session.cookie')),
                    'session_cookie_name' => config('session.cookie'),
                    'is_secure' => $request->isSecure(),
                    'scheme_forwarded' => $request->header('X-Forwarded-Proto'),
                    'host' => $request->getHost(),
                    'full_url' => $request->fullUrl(),
                    'query_state' => $request->query('state'),
                ]);
            }

            return $this->failLogin('Phiên đăng nhập đã hết hạn. Vui lòng thử lại.');
        }

        try {
            $user = $this->authenticator->authenticate($googleUser);
        } catch (EmailDomainNotAllowed) {
            $allowed = implode(', ', (array) config('services.google.allowed_domains'));

            return $this->failLogin("Chỉ tài khoản thuộc {$allowed} mới được đăng nhập.");
        } catch (AccountNotUsable) {
            return $this->failLogin('Tài khoản của bạn hiện không thể đăng nhập. Vui lòng liên hệ quản trị viên.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $redirect = $request->session()->pull('login.redirect');

        return redirect()->to($this->frontendUrl('/auth/callback', [
            'status' => 'ok',
            'redirect' => $redirect,
        ]));
    }

    public function logout(Request $request, ViewAsService $viewAs): RedirectResponse|JsonResponse
    {
        $viewAs->deactivate();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Logged out']);
        }

        return redirect()->to($this->frontendUrl('/login'));
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function failLogin(string $message): RedirectResponse
    {
        return redirect()->to($this->frontendUrl('/login', ['error' => $message]));
    }

    /** @param  array<string, string|null>  $query */
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

        // Chặn protocol-relative ("//evil.com") giả dạng path nội bộ.
        if (str_starts_with($redirect, '//')) {
            return null;
        }

        return $redirect;
    }
}
