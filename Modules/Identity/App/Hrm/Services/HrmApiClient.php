<?php

namespace Modules\Identity\App\Hrm\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Identity\App\Hrm\DTO\HrmEmployeeData;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;

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
        return Http::baseUrl((string) config('services.hrm.api_base_url'))
            ->withToken((string) config('services.hrm.api_token'))
            ->timeout(5)
            ->retry(2, 200, throw: false)
            ->acceptJson();
    }
}
