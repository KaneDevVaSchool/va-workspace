<?php

namespace Modules\Identity\App\Hrm\Services;

use Illuminate\Http\Client\ConnectionException;
use Modules\Identity\App\Hrm\DTO\HrmEmployeeData;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Exceptions\HrmTokenInvalid;

/**
 * Wrapper HTTP gọi API VA-HRM (GET /api/v1/employees/*, /companies,
 * /org-units) — Sanctum Bearer token của ApiClient va-workspace đã đăng
 * ký bên HRM (abilities: employees:read, org:read).
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
        if (in_array($status, [400, 401, 403, 422], true)) {
            $message = $response->json('error.message') ?? 'token không hợp lệ';

            throw new HrmTokenInvalid((string) $message);
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
}
