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
    /**
     * Payload thô EmployeeResource — dùng khi cần field ngoài DTO (vd. nhân sự phụ trách).
     *
     * @return array<string, mixed>|null
     *
     * @throws HrmApiUnavailable
     */
    /**
     * @param  array<string, scalar|null>  $query
     */
    public function getEmployeePayload(string $uuid, array $query = []): ?array
    {
        return $this->get("/api/v1/employees/{$uuid}", $query);
    }

    /**
     * Cùng endpoint employee show nhưng qua base portal (HRM_LEAVE_API_*)
     * — thường có field mới (vd. hr_owner) trước khi đồng bộ lên hrm.vaschools.edu.vn.
     *
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>|null
     *
     * @throws HrmApiUnavailable
     */
    public function getEmployeePayloadFromLeaveCatalog(string $uuid, array $query = []): ?array
    {
        return $this->getFrom(HrmOutboundHttp::leaveCatalogClient(), "/api/v1/employees/{$uuid}", $query);
    }

    /** @throws HrmApiUnavailable */
    public function getEmployee(string $uuid): ?HrmEmployeeData
    {
        $response = $this->getEmployeePayload($uuid);

        if ($response === null) {
            return null;
        }

        $managerFullName = $this->getEmployeeManagerFullName($uuid);

        return HrmEmployeeData::fromArray($response, $managerFullName);
    }

    /** @throws HrmApiUnavailable */
    public function getEmployeeManagerFullName(string $uuid): ?string
    {
        $manager = $this->getEmployeeManager($uuid);

        return $manager['full_name'] ?? null;
    }

    /**
     * Quản lý trực tiếp — GET /api/v1/employees/{uuid}/manager (EmployeeResource).
     *
     * @return array<string, mixed>|null
     *
     * @throws HrmApiUnavailable
     */
    public function getEmployeeManager(string $uuid): ?array
    {
        return $this->get("/api/v1/employees/{$uuid}/manager");
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
     * Loại đơn nghỉ đang hoạt động — đồng bộ portal HRM /leave/types (ability leave:read).
     *
     * @return list<array<string, mixed>>
     *
     * @throws HrmApiUnavailable
     */
    public function listLeaveTypes(): array
    {
        $data = $this->getFrom(HrmOutboundHttp::leaveCatalogClient(), '/api/v1/leave/types');

        return is_array($data) ? $data : [];
    }

    /**
     * Tạo đơn nghỉ — POST /api/v1/leave/requests (ability leave:write, portal/HRM_LEAVE_API_*).
     *
     * @param  array<string, scalar|null>  $fields
     * @param  list<\Illuminate\Http\UploadedFile>  $attachments
     * @return array<string, mixed>
     *
     * @throws HrmApiUnavailable
     */
    public function createLeaveRequest(array $fields, array $attachments = []): array
    {
        $http = HrmOutboundHttp::leaveCatalogClient(60);

        foreach ($attachments as $file) {
            $http = $http->attach(
                'attachments[]',
                fopen($file->getRealPath(), 'r'),
                $file->getClientOriginalName(),
            );
        }

        try {
            $response = $http->post('/api/v1/leave/requests', $fields);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable('timeout/network lỗi khi gọi POST /api/v1/leave/requests', $e);
        }

        if ($response->status() === 422) {
            $errors = $response->json('errors') ?? $response->json('error.errors');
            $message = is_array($errors)
                ? collect($errors)->flatten()->first()
                : (string) ($response->json('message') ?? 'Dữ liệu đơn nghỉ không hợp lệ.');

            throw new HrmApiUnavailable((string) $message);
        }

        if (! $response->successful()) {
            throw new HrmApiUnavailable("HTTP {$response->status()} khi gọi POST /api/v1/leave/requests");
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    /**
     * Danh sách đơn nghỉ của nhân sự — GET /api/v1/leave/requests.
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     *
     * @throws HrmApiUnavailable
     */
    public function listLeaveRequests(string $employeeUuid, int $perPage = 20): array
    {
        try {
            $response = HrmOutboundHttp::leaveCatalogClient()->get('/api/v1/leave/requests', [
                'employee_uuid' => $employeeUuid,
                'per_page' => $perPage,
            ]);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable('timeout/network lỗi khi gọi GET /api/v1/leave/requests', $e);
        }

        if (! $response->successful()) {
            throw new HrmApiUnavailable("HTTP {$response->status()} khi gọi GET /api/v1/leave/requests");
        }

        $data = $response->json('data');

        return [
            'items' => is_array($data) ? $data : [],
            'meta' => is_array($response->json('meta')) ? $response->json('meta') : [],
        ];
    }

    /**
     * Toàn bộ đơn vị tổ chức (cursor paginate trên HRM, tối đa 200/trang).
     *
     * @param  array<string, scalar|null>  $filters  company, parent, type, …
     * @return list<array<string, mixed>>
     *
     * @throws HrmApiUnavailable
     */
    public function listAllOrgUnits(array $filters = []): array
    {
        $items = [];
        $cursor = null;

        do {
            $query = array_merge(['per_page' => 200], $filters);
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }

            $page = $this->listOrgUnitsPage($query);
            $items = array_merge($items, $page['items']);
            $cursor = $page['next_cursor'];
        } while ($cursor !== null && $cursor !== '');

        return $items;
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array{items: list<array<string, mixed>>, next_cursor: ?string}
     *
     * @throws HrmApiUnavailable
     */
    public function listOrgUnitsPage(array $query = []): array
    {
        try {
            $response = $this->client()->get('/api/v1/org-units', $query);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable('timeout/network lỗi khi gọi /api/v1/org-units', $e);
        }

        if (! $response->successful()) {
            throw new HrmApiUnavailable("HTTP {$response->status()} khi gọi /api/v1/org-units");
        }

        $data = $response->json('data');

        return [
            'items' => is_array($data) ? $data : [],
            'next_cursor' => $response->json('meta.cursor.next'),
        ];
    }

    /**
     * Toàn bộ nhân viên VA-HRM (cursor paginate, ability employees:read).
     * Dùng cho superadmin xem "Nhân sự workspace" — kéo hết 1 lần, không
     * lọc theo `q`/`status` phía HRM (lọc client-side sau khi tải).
     *
     * @return list<array<string, mixed>>
     *
     * @throws HrmApiUnavailable
     */
    public function listAllEmployees(): array
    {
        $items = [];
        $cursor = null;

        do {
            $query = ['per_page' => 200];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }

            $page = $this->listEmployeesPage($query);
            $items = array_merge($items, $page['items']);
            $cursor = $page['next_cursor'];
        } while ($cursor !== null && $cursor !== '');

        return $items;
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array{items: list<array<string, mixed>>, next_cursor: ?string}
     *
     * @throws HrmApiUnavailable
     */
    /**
     * Tra cứu theo mã NV (exact) — dùng cho «Cấp trên trực tiếp» snapshot (VA010067).
     *
     * @return array<string, mixed>|null
     *
     * @throws HrmApiUnavailable
     */
    public function findEmployeeByCode(string $code): ?array
    {
        $needle = strtoupper(trim($code));
        if ($needle === '') {
            return null;
        }

        $page = $this->listEmployeesPage(['q' => $needle, 'per_page' => 50]);
        foreach ($page['items'] as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (strtoupper(trim((string) ($item['code'] ?? ''))) === $needle) {
                return $item;
            }
        }

        return null;
    }

    public function listEmployeesPage(array $query = []): array
    {
        try {
            $response = $this->client()->get('/api/v1/employees', $query);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable('timeout/network lỗi khi gọi /api/v1/employees', $e);
        }

        if (! $response->successful()) {
            throw new HrmApiUnavailable("HTTP {$response->status()} khi gọi /api/v1/employees");
        }

        $data = $response->json('data');

        return [
            'items' => is_array($data) ? $data : [],
            'next_cursor' => $response->json('meta.cursor.next'),
        ];
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

        if ($this->isVerifyTokenAbilityDenied($message, $response->json('error.code'))) {
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
    /**
     * @param  array<string, scalar|null>  $query
     */
    private function get(string $path, array $query = []): ?array
    {
        return $this->getFrom($this->client(), $path, $query);
    }

    /**
     * @param  array<string, scalar|null>  $query
     *
     * @throws HrmApiUnavailable
     */
    private function getFrom(\Illuminate\Http\Client\PendingRequest $http, string $path, array $query = []): ?array
    {
        try {
            $response = $query === [] ? $http->get($path) : $http->get($path, $query);
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
