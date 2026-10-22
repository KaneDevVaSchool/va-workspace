<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
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
     * UUID nhân sự trên portal nghỉ phép — có thể khác hrm_employee_uuid (SSO HRM chính).
     *
     * @throws HrmApiUnavailable
     */
    public function resolveLeaveCatalogEmployeeUuid(User $user): string
    {
        $uuid = trim((string) $user->hrm_employee_uuid);
        if ($uuid !== '') {
            try {
                if ($this->getEmployeePayloadFromLeaveCatalog($uuid) !== null) {
                    return $uuid;
                }
            } catch (HrmApiUnavailable $e) {
                if ($e->isConnectionFailure()) {
                    throw $e;
                }
            }
        }

        $email = trim((string) $user->email);
        if ($email === '') {
            throw HrmApiUnavailable::userFacing('Tài khoản chưa liên kết nhân sự HRM (portal nghỉ phép).');
        }

        try {
            $response = HrmOutboundHttp::leaveCatalogClient(15)->get('/api/v1/employees', [
                'email' => $email,
                'per_page' => 1,
            ]);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable('timeout/network lỗi khi tra cứu nhân sự portal HRM', $e);
        }

        if (! $response->successful()) {
            $this->failLeaveCatalogResponse($response, 'GET /api/v1/employees');
        }

        $data = $response->json('data');
        $row = is_array($data) ? ($data[0] ?? null) : null;
        $portalUuid = is_array($row) ? trim((string) ($row['uuid'] ?? '')) : '';
        if ($portalUuid === '') {
            throw HrmApiUnavailable::userFacing('Không tìm thấy hồ sơ nhân sự trên portal HRM cho email này.');
        }

        return $portalUuid;
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
            throw HrmApiUnavailable::userFacing(
                $this->humanizeLeaveRequestErrorMessage($this->firstLeaveRequestErrorMessage($response)),
            );
        }

        if ($response->status() === 403) {
            $code = $response->json('error.code');
            $message = (string) ($response->json('error.message') ?? '');
            if ($code === 'MISSING_ABILITY' || $this->isVerifyTokenAbilityDenied($message, $code)) {
                throw HrmApiUnavailable::userFacing(
                    'Hệ thống chưa được cấp quyền gửi đơn nghỉ trên HRM. Liên hệ IT hoặc HR.'
                );
            }
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('error.message') ?? '');
            if ($message !== '') {
                throw HrmApiUnavailable::userFacing($message);
            }

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
            $response = HrmOutboundHttp::leaveCatalogClient(15)->get('/api/v1/leave/requests', [
                'employee_uuid' => $employeeUuid,
                'per_page' => $perPage,
            ]);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable('timeout/network lỗi khi gọi GET /api/v1/leave/requests', $e);
        }

        if (! $response->successful()) {
            $this->failLeaveCatalogResponse($response, 'GET /api/v1/leave/requests');
        }

        $data = $response->json('data');

        return [
            'items' => is_array($data) ? $data : [],
            'meta' => is_array($response->json('meta')) ? $response->json('meta') : [],
        ];
    }

    /**
     * Số dư phép năm — GET /api/v1/leave/balances (ability leave:read).
     *
     * @return array<string, mixed>
     *
     * @throws HrmApiUnavailable
     */
    public function getLeaveBalance(string $employeeUuid, ?int $year = null): array
    {
        $query = ['employee_uuid' => $employeeUuid];
        if ($year !== null) {
            $query['year'] = $year;
        }

        try {
            $response = HrmOutboundHttp::leaveCatalogClient(15)->get('/api/v1/leave/balances', $query);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable('timeout/network lỗi khi gọi GET /api/v1/leave/balances', $e);
        }

        if (! $response->successful()) {
            $this->failLeaveCatalogResponse($response, 'GET /api/v1/leave/balances');
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    /**
     * Bảng chấm công chờ Trưởng đơn vị duyệt (scope=pending) hoặc đã xử lý (scope=history) —
     * GET /api/v1/attendance/timesheet-approvals (ability attendance:approve).
     *
     * @return list<array<string, mixed>>
     *
     * @throws HrmApiUnavailable
     */
    public function listTimesheetApprovals(string $employeeUuid, string $scope = 'pending'): array
    {
        $response = $this->timesheetRequest('get', '/api/v1/attendance/timesheet-approvals', [
            'employee_uuid' => $employeeUuid,
            'scope' => $scope,
        ]);

        $data = $response->json('data');

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * Chi tiết bảng chấm công (tổng hợp công + lịch sử) — GET /api/v1/attendance/timesheet-approvals/{uuid}.
     *
     * @return array<string, mixed>|null null khi không tìm thấy
     *
     * @throws HrmApiUnavailable
     */
    public function getTimesheetApproval(string $confirmationUuid, string $employeeUuid): ?array
    {
        $response = $this->timesheetRequest(
            'get',
            "/api/v1/attendance/timesheet-approvals/{$confirmationUuid}",
            ['employee_uuid' => $employeeUuid],
            allowNotFound: true,
        );

        if ($response === null) {
            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    /**
     * Duyệt / từ chối — POST /api/v1/attendance/timesheet-approvals/{uuid}/{approve|reject}.
     *
     * @return array<string, mixed>
     *
     * @throws HrmApiUnavailable lỗi nghiệp vụ (đã xử lý, không phải Trưởng đơn vị…) mang thông điệp userFacing
     */
    public function decideTimesheetApproval(
        string $confirmationUuid,
        string $employeeUuid,
        bool $approve,
        ?string $reason = null,
    ): array {
        $payload = ['employee_uuid' => $employeeUuid];
        if (! $approve) {
            $payload['reason'] = (string) $reason;
        }

        $response = $this->timesheetRequest(
            'post',
            "/api/v1/attendance/timesheet-approvals/{$confirmationUuid}/".($approve ? 'approve' : 'reject'),
            $payload,
        );

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    /**
     * Giờ chấm một ngày — GET /api/v1/attendance/punches?from=to=$workDate.
     *
     * @return array<string, mixed>|null phần tử đầu, hoặc null khi chưa có giờ
     *
     * @throws HrmApiUnavailable
     */
    public function getWorkspacePunch(string $employeeUuid, string $workDate): ?array
    {
        $response = $this->timesheetRequest('get', '/api/v1/attendance/punches', [
            'employee_uuid' => $employeeUuid,
            'from' => $workDate,
            'to' => $workDate,
        ], forbiddenMessage: 'Workspace chưa được cấp quyền gửi giờ chấm công về HRM. Liên hệ IT.');

        $data = $response?->json('data');
        if (! is_array($data) || $data === []) {
            return null;
        }

        $first = array_is_list($data) ? ($data[0] ?? null) : $data;

        return is_array($first) ? $first : null;
    }

    /**
     * Ghi giờ vào/ra — POST /api/v1/attendance/punches.
     *
     * @param  array{
     *     work_date: string,
     *     check_in_time?: string|null,
     *     morning_check_out_time?: string|null,
     *     afternoon_check_in_time?: string|null,
     *     check_out_time?: string|null,
     *     check_in_method?: string|null,
     *     morning_check_out_method?: string|null,
     *     afternoon_check_in_method?: string|null,
     *     check_out_method?: string|null
     * }  $fields
     * @return array<string, mixed>
     *
     * @throws HrmApiUnavailable
     */
    public function upsertWorkspacePunch(string $employeeUuid, array $fields): array
    {
        $response = $this->timesheetRequest(
            'post',
            '/api/v1/attendance/punches',
            array_merge($fields, ['employee_uuid' => $employeeUuid]),
            forbiddenMessage: 'Workspace chưa được cấp quyền gửi giờ chấm công về HRM. Liên hệ IT.',
        );

        $data = $response?->json('data');

        return is_array($data) ? $data : [];
    }

    /**
     * @param  'get'|'post'  $method
     * @param  array<string, scalar|null>  $payload
     *
     * @throws HrmApiUnavailable
     */
    private function timesheetRequest(
        string $method,
        string $path,
        array $payload,
        bool $allowNotFound = false,
        ?string $forbiddenMessage = null,
    ): ?\Illuminate\Http\Client\Response {
        try {
            $http = HrmOutboundHttp::timesheetClient();
            $response = $method === 'post' ? $http->post($path, $payload) : $http->get($path, $payload);
        } catch (ConnectionException $e) {
            throw new HrmApiUnavailable("timeout/network lỗi khi gọi {$path}", $e);
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        if ($status === 404 && $allowNotFound) {
            return null;
        }

        $code = $response->json('error.code');
        $message = (string) ($response->json('error.message') ?? '');

        if ($status === 401 || $code === 'UNAUTHENTICATED') {
            throw HrmApiUnavailable::userFacing(
                'Token API HRM không hợp lệ hoặc đã thu hồi. Liên hệ IT cập nhật HRM_API_TOKEN / HRM_TIMESHEET_API_TOKEN trên Workspace.'
            );
        }

        if ($status === 403 && $code === 'MISSING_ABILITY') {
            throw HrmApiUnavailable::userFacing(
                $forbiddenMessage ?? 'Workspace chưa được cấp quyền duyệt bảng chấm công trên HRM. Liên hệ IT.'
            );
        }

        if ($status === 403) {
            throw HrmApiUnavailable::userFacing(
                $forbiddenMessage ?? (
                    $message !== '' && $code !== 'FORBIDDEN'
                        ? $message
                        : 'Anh/chị không phải Trưởng đơn vị của phòng này.'
                )
            );
        }

        if ($status === 404) {
            throw HrmApiUnavailable::userFacing('Không tìm thấy bảng chấm công.');
        }

        if ($status === 422) {
            $errors = $response->json('error.details.errors');
            $first = is_array($errors) ? collect($errors)->flatten()->first() : null;

            throw HrmApiUnavailable::userFacing(
                is_string($first) && $first !== '' ? $first : 'Dữ liệu không hợp lệ.'
            );
        }

        throw new HrmApiUnavailable("HTTP {$status} khi gọi {$path}");
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

    private function firstLeaveRequestErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $errors = $response->json('errors')
            ?? $response->json('error.errors')
            ?? $response->json('error.details.errors');

        if (is_array($errors)) {
            $first = collect($errors)->flatten()->first();
            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        $message = (string) ($response->json('error.message') ?? $response->json('message') ?? '');
        if ($message !== '' && ! str_contains(mb_strtolower($message), 'không hợp lệ')) {
            return $message;
        }

        return 'Không gửi được đơn nghỉ. Kiểm tra lại thông tin trên form.';
    }

    /**
     * @throws HrmApiUnavailable
     */
    private function failLeaveCatalogResponse(\Illuminate\Http\Client\Response $response, string $label): void
    {
        $status = $response->status();

        if ($status === 403) {
            $code = $response->json('error.code');
            $message = (string) ($response->json('error.message') ?? '');
            if ($code === 'MISSING_ABILITY' || $this->isVerifyTokenAbilityDenied($message, $code)) {
                throw HrmApiUnavailable::userFacing(
                    'Hệ thống chưa được cấp quyền nghỉ phép trên HRM. Liên hệ IT hoặc HR.'
                );
            }
        }

        if ($status >= 500) {
            throw new HrmApiUnavailable("HTTP {$status} khi gọi {$label}");
        }

        $message = (string) ($response->json('error.message') ?? '');
        if ($message === '') {
            $message = $this->firstLeaveRequestErrorMessage($response);
        }

        if ($message !== '') {
            throw HrmApiUnavailable::userFacing($message);
        }

        throw HrmApiUnavailable::userFacing("Không thực hiện được yêu cầu trên HRM (HTTP {$status}).");
    }

    private function humanizeLeaveRequestErrorMessage(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 'Không gửi được đơn nghỉ. Kiểm tra lại thông tin trên form.';
        }

        if (preg_match('/Quỹ phép năm không đủ \(khả dụng ([^,]+), cần ([^)]+)\)/u', $raw, $m)) {
            return "Không đủ phép năm: còn {$m[1]}, đơn này cần {$m[2]}. Liên hệ HR nếu số dư trên HRM chưa đúng.";
        }

        if (str_contains($raw, 'Dữ liệu đơn nghỉ không hợp lệ')
            || str_contains(mb_strtolower($raw), 'không hợp lệ')) {
            return 'Không gửi được đơn nghỉ. Kiểm tra loại nghỉ, ngày và giấy tờ đính kèm.';
        }

        return $raw;
    }
}
