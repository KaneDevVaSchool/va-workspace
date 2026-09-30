<?php

namespace Modules\Identity\App\Hrm\Services;

use Illuminate\Http\Client\ConnectionException;
use Modules\Identity\App\Hrm\DTO\HrmEmployeeData;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Exceptions\HrmTokenInvalid;

/**
 * Wrapper HTTP gọi API VA-HRM (GET /api/v1/employees/*, /companies,
 * /org-units) — Sanctum Bearer token của ApiClient va-workspace đã đăng
 * ký bên HRM. Abilities tối thiểu: employees:read, org:read; khi JWKS chưa có
 * trên HRM cần thêm quyền gọi POST /api/v1/auth/verify-token (tên ability
 * do admin HRM cấp — thường dạng auth:verify-token / sso:verify).
 */
class HrmApiClient
{
    /** @throws HrmApiUnavailable */
    public function getEmployee(string $uuid): ?HrmEmployeeData
    {
        $response = $this->get("/api/v1/employees/{$uuid}");

        if ($response === null) {
            return null;
        }

        $managerFullName = $this->getEmployeeManagerFullName($uuid);

        return HrmEmployeeData::fromArray($response, $managerFullName);
    }

    /** @throws HrmApiUnavailable */
    public function getEmployeeManagerFullName(string $uuid): ?string
    {
        $manager = $this->get("/api/v1/employees/{$uuid}/manager");

        return $manager['full_name'] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function getOrgUnit(string $uuid): ?array
    {
        return $this->get("/api/v1/org-units/{$uuid}");
    }

    /** @return list<array<string, mixed>> */
    public function getCompanies(): array
    {
        return $this->get('/api/v1/companies') ?? [];
    }

    /**
     * Xác thực JWT SSO qua HRM (fallback khi JWKS không khả dụng).
     *
     * @return array<string, mixed> claims
     *
     * @throws HrmApiUnavailable
     * @throws HrmTokenInvalid
     */
    public function verifySsoToken(string $jwt): array
    {
        try {
            $response = $this->client()->post('/api/v1/auth/verify-token', ['token' => $jwt]);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable('timeout/network lỗi khi verify SSO token', $e);
        }

        if ($response->successful()) {
            $data = $response->json('data');
            if (! is_array($data) || $data === []) {
                throw new HrmApiUnavailable('verify-token không trả data claims');
            }

            return $data;
        }

        $status = $response->status();
        $message = (string) ($response->json('error.message') ?? 'token không hợp lệ');

        if ($status === 403 || $this->isVerifyTokenAbilityDenied($message, $response->json('error.code'))) {
            throw new HrmApiUnavailable(
                'ApiClient va-workspace thiếu ability gọi verify-token — cấp quyền trên admin HRM hoặc publish JWKS'
            );
        }

        if (in_array($status, [400, 401, 422], true)) {
            throw new HrmTokenInvalid($message);
        }

        throw new HrmApiUnavailable("HTTP {$status} khi gọi verify-token");
    }

    /**
     * @return array<string, mixed>|null
     * @throws HrmApiUnavailable
     */
    private function get(string $path): ?array
    {
        try {
            $response = $this->client()->get($path);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable("timeout/network lỗi khi gọi {$path}", $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new HrmApiUnavailable("HTTP {$response->status()} khi gọi {$path}");
        }

        return $response->json('data');
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return HrmOutboundHttp::client(withApiToken: true);
    }

    private function isVerifyTokenAbilityDenied(string $message, mixed $errorCode): bool
    {
        if (is_string($errorCode) && in_array($errorCode, ['FORBIDDEN', 'INSUFFICIENT_ABILITIES'], true)) {
            return true;
        }

        $lower = mb_strtolower($message);

        return str_contains($lower, 'ability') || str_contains($lower, 'quyền');
    }
}
