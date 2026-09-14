<?php

namespace Modules\Credential\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Modules\Credential\App\Enums\CredentialEnums;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Repositories\Contracts\CredentialRepositoryInterface;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Business logic module Credential. Nơi DUY NHẤT quyết định field nhạy cảm
 * (chỉ `password` — xem present()) có được trả ra cho viewer hay không —
 * tách bạch với quyền CRUD (permission:credential.manage): 1 user có quyền
 * sửa/xoá bản ghi vẫn có thể KHÔNG được xem mật khẩu nếu không phải
 * creator/viewer được cấp riêng (bảng credential_viewers).
 */
class CredentialService
{
    public function __construct(
        private readonly CredentialRepositoryInterface $credentials,
        private readonly PermissionService $permissions,
        private readonly CredentialExcelExporter $excelExporter,
        private readonly ActivityLogService $activityLogs,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage, int $page, User $viewer): LengthAwarePaginator
    {
        return $this->credentials->paginate($filters, $perPage, $page, $this->departmentScopeFor($viewer), $viewer->id);
    }

    public function find(int $id, User $viewer): ?Credential
    {
        return $this->credentials->find($id, $this->departmentScopeFor($viewer), $viewer->id);
    }

    /**
     * Phạm vi phòng ban dùng để lọc dữ liệu — null = KHÔNG giới hạn (chỉ
     * superadmin hoặc role đang giữ `credential.manage` scope GLOBAL, xem
     * mục "Vai trò nào được xem toàn bộ" đã thống nhất). Mọi viewer khác
     * chỉ thấy credential thuộc đúng phòng ban mình (department_id null =
     * "chưa gán phòng ban", KHÔNG ai tự thấy qua nhánh này) — trừ khi được
     * cấp quyền xem riêng qua credential_viewers (Repository tự cộng thêm
     * nhánh đó qua $viewerId, xem CredentialRepository::applyDepartmentScope()).
     *
     * @return list<int>|null
     */
    public function departmentScopeFor(User $viewer): ?array
    {
        if ($viewer->isSuperAdmin() || $viewer->allows('credential.manage')) {
            return null;
        }

        return $viewer->department_id !== null ? [(int) $viewer->department_id] : [];
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $creator): Credential
    {
        $data['created_by'] = $creator->id;
        $data['updated_by'] = $creator->id;
        $data['expires_at'] = $this->computeExpiresAt($data['purchased_at'] ?? null);

        // Chuỗi rỗng ('' từ payload frontend) sẽ đè mất default DB
        // ('external') nếu gán thẳng null — chuẩn hoá về giá trị mặc định
        // thay vì để trống.
        if (empty($data['group'])) {
            $data['group'] = CredentialEnums::GROUP_EXTERNAL;
        }

        // Mặc định gán theo phòng ban người tạo — chỉ viewer có phạm vi
        // không giới hạn (departmentScopeFor() === null, tức global/
        // superadmin) mới được đổi sang phòng ban khác qua request (xem
        // StoreCredentialRequest, field department_id chỉ validate khi có
        // quyền này). Người tạo thường (bị giới hạn theo phòng ban) luôn
        // bị ép về đúng phòng ban mình, bỏ qua department_id gửi lên.
        $canChooseDepartment = $this->departmentScopeFor($creator) === null;
        if (! $canChooseDepartment || ! array_key_exists('department_id', $data)) {
            $data['department_id'] = $creator->department_id;
        }

        $credential = $this->credentials->create($data);

        $this->activityLogs->record(
            'credential.create',
            "Tạo thông tin đăng nhập \"{$credential->name}\"",
            $creator,
            'credential',
            $credential->id,
        );

        return $credential;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Credential $credential, array $data, User $updater): Credential
    {
        $data['updated_by'] = $updater->id;

        // Chỉ viewer có phạm vi không giới hạn (global/superadmin) mới
        // được đổi department_id của credential đã tồn tại — người khác
        // gửi field này (nếu có) sẽ bị bỏ qua, không đổi phòng ban sở hữu.
        if ($this->departmentScopeFor($updater) !== null) {
            unset($data['department_id']);
        }

        // Chỉ tính lại expires_at khi request này có gửi purchased_at —
        // tránh vô tình xoá hạn dùng khi client chỉ sửa field khác.
        if (array_key_exists('purchased_at', $data)) {
            $data['expires_at'] = $this->computeExpiresAt($data['purchased_at']);
        }

        $updated = $this->credentials->update($credential, $data);

        $this->activityLogs->record(
            'credential.update',
            "Cập nhật thông tin đăng nhập \"{$updated->name}\"",
            $updater,
            'credential',
            $updated->id,
        );

        return $updated;
    }

    /**
     * Ngày hết hạn = ngày mua + 1 tháng (chu kỳ thuê bao hàng tháng, vd.
     * Claude Pro) — KHÔNG cho nhập tay (xem StoreCredentialRequest/
     * UpdateCredentialRequest, đã bỏ rule `expires_at`). Dùng
     * addMonthNoOverflow để tránh lỗi tràn ngày cuối tháng (31/1 + 1 tháng
     * phải ra 28/2, không phải 3/3). Không có ngày mua → không có hạn dùng
     * (null, giữ hành vi "luôn active" của getStatusAttribute()).
     */
    private function computeExpiresAt(?string $purchasedAt): ?string
    {
        if (empty($purchasedAt)) {
            return null;
        }

        return Carbon::parse($purchasedAt)->addMonthNoOverflow(1)->toDateString();
    }

    public function delete(Credential $credential): bool
    {
        $name = $credential->name;
        $id = $credential->id;
        $result = $this->credentials->delete($credential);

        $this->activityLogs->record(
            'credential.delete',
            "Xoá thông tin đăng nhập \"{$name}\"",
            subjectType: 'credential',
            subjectId: $id,
        );

        return $result;
    }

    public function canSeeSecret(Credential $credential, User $viewer): bool
    {
        if ($credential->created_by === $viewer->id) {
            return true;
        }

        return $credential->viewers->contains('id', $viewer->id);
    }

    public function canManage(User $viewer): bool
    {
        return $this->permissions->allows($viewer, 'credential.manage');
    }

    public function addViewer(Credential $credential, int $userId, User $grantedBy): void
    {
        $this->credentials->addViewer($credential, $userId, $grantedBy->id);

        $targetName = User::find($userId)?->name ?? "#{$userId}";
        $this->activityLogs->record(
            'credential_viewer.grant',
            "Chia sẻ quyền xem \"{$credential->name}\" cho \"{$targetName}\"",
            $grantedBy,
            'credential',
            $credential->id,
        );
    }

    public function removeViewer(Credential $credential, int $userId): void
    {
        $this->credentials->removeViewer($credential, $userId);

        $targetName = User::find($userId)?->name ?? "#{$userId}";
        $this->activityLogs->record(
            'credential_viewer.revoke',
            "Thu hồi quyền xem \"{$credential->name}\" của \"{$targetName}\"",
            subjectType: 'credential',
            subjectId: $credential->id,
        );
    }

    /**
     * Đồng bộ toàn bộ viewer về đúng danh sách $userIds trong 1 lần —
     * dùng cho picker multi-select ở CredentialDetail.vue (chọn/bỏ nhiều
     * người rồi bấm "Lưu thay đổi" 1 lần, thay vì gọi addViewer/removeViewer
     * tuần tự từng người). Giữ nguyên granted_by/created_at của người
     * không đổi (xem CredentialRepository::syncViewers()).
     *
     * @param  list<int>  $userIds
     */
    public function syncViewers(Credential $credential, array $userIds, User $grantedBy): void
    {
        $this->credentials->syncViewers($credential, $userIds, $grantedBy->id);

        $this->activityLogs->record(
            'credential_viewer.sync',
            "Đồng bộ danh sách người được xem \"{$credential->name}\"",
            $grantedBy,
            'credential',
            $credential->id,
            ['viewer_count' => count($userIds)],
        );
    }

    /** @return array<string, int> */
    public function statusCounts(User $viewer): array
    {
        return $this->credentials->statusCounts($this->departmentScopeFor($viewer), $viewer->id);
    }

    /** Danh sách user tối giản — dùng cho chọn người được cấp quyền xem dữ liệu nhạy cảm. */
    public function allUsers()
    {
        return $this->credentials->allUsers();
    }

    /**
     * Dự toán chi phí — tổng hợp monthly_cost của mọi tài khoản đang tính
     * phí (bỏ qua bản ghi ẩn chi phí hoặc không có chi phí), quy đổi mọi
     * loại tiền về VND bằng CredentialEnums::USD_TO_VND để cộng dồn được.
     * Chia theo nhà cung cấp (tối đa 3 nhóm màu chính, còn lại gộp "Khác" —
     * xem CredentialEnums::COST_CHART_MAX_SLICES) và theo loại tài khoản.
     *
     * @return array<string, mixed>
     */
    public function costSummary(User $viewer): array
    {
        $items = $this->credentials->allWithCost($this->departmentScopeFor($viewer), $viewer->id);

        $toVnd = fn (float $amount, string $currency): float => strtoupper($currency) === 'USD'
            ? $amount * CredentialEnums::USD_TO_VND
            : $amount;

        $totalMonthly = 0.0;
        $byProvider = [];
        $byAccountType = [];

        foreach ($items as $credential) {
            $amountVnd = $toVnd((float) $credential->monthly_cost, $credential->currency);
            $totalMonthly += $amountVnd;

            $providerName = $credential->provider->name ?? 'Chưa phân loại';
            $byProvider[$providerName] = ($byProvider[$providerName] ?? 0) + $amountVnd;

            $typeLabel = CredentialEnums::ACCOUNT_TYPE_LABELS[$credential->account_type] ?? $credential->account_type;
            $byAccountType[$typeLabel] = ($byAccountType[$typeLabel] ?? 0) + $amountVnd;
        }

        arsort($byProvider);
        arsort($byAccountType);

        $sheetItems = $items->map(fn (Credential $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'provider' => $c->provider->name ?? 'Chưa phân loại',
            'account_type' => CredentialEnums::ACCOUNT_TYPE_LABELS[$c->account_type] ?? $c->account_type,
            'monthly_cost_vnd' => round($toVnd((float) $c->monthly_cost, $c->currency)),
            'purchased_at' => optional($c->purchased_at)->toDateString(),
            'expires_at' => optional($c->expires_at)->toDateString(),
            'status' => $c->status,
        ])->values();

        return [
            'currency' => 'VND',
            'total_monthly' => round($totalMonthly),
            'total_yearly_estimate' => round($totalMonthly * 12),
            'account_count' => $items->count(),
            'by_provider' => $this->collapseToSlices($byProvider),
            'by_account_type' => $this->collapseToSlices($byAccountType),
            'items' => $sheetItems,
        ];
    }

    /**
     * Dự phóng chi phí 12 tháng tới — KHÔNG phải dữ liệu lịch sử: mỗi
     * tháng lấy đúng tổng chi phí hiện tại (giả định không đổi gì), kèm
     * danh sách tài khoản hết hạn rơi vào tháng đó (từ expires_at thật)
     * để biết tháng nào cần quyết định gia hạn. Không suy diễn xu hướng
     * tăng/giảm giả — field `basis`/`note` trong response phải luôn hiện
     * rõ trên UI để người xem không hiểu nhầm đây là số liệu lịch sử.
     *
     * @return array<string, mixed>
     */
    public function costForecast(User $viewer): array
    {
        $items = $this->credentials->allWithCost($this->departmentScopeFor($viewer), $viewer->id);

        $toVnd = fn (float $amount, string $currency): float => strtoupper($currency) === 'USD'
            ? $amount * CredentialEnums::USD_TO_VND
            : $amount;

        $totalMonthly = round($items->sum(fn (Credential $c) => $toVnd((float) $c->monthly_cost, $c->currency)));

        $months = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $monthKey = $cursor->format('Y-m');
            $expiring = $items
                ->filter(fn (Credential $c) => $c->expires_at && $c->expires_at->format('Y-m') === $monthKey)
                ->map(fn (Credential $c) => ['name' => $c->name, 'expires_at' => $c->expires_at->toDateString()])
                ->values();

            $months[] = [
                'month' => $monthKey,
                'total_vnd' => $totalMonthly,
                'expiring' => $expiring,
            ];

            $cursor->addMonthNoOverflow();
        }

        return [
            'currency' => 'VND',
            'basis' => 'projection',
            'note' => 'Dự phóng dựa trên chi phí hiện tại, giả định không đổi trong 12 tháng tới.',
            'months' => $months,
        ];
    }

    /**
     * Gộp các nhóm nhỏ vào "Khác" khi vượt quá số lát tối đa cho biểu đồ
     * (COST_CHART_MAX_SLICES) — không sinh thêm màu categorical cho nhóm
     * thứ N, giữ đúng số hue cố định của theme.
     *
     * @param  array<string, float>  $amounts  đã arsort giảm dần
     * @return list<array{label: string, amount: float}>
     */
    private function collapseToSlices(array $amounts): array
    {
        $max = CredentialEnums::COST_CHART_MAX_SLICES;
        $entries = array_map(fn ($label, $amount) => ['label' => $label, 'amount' => round($amount)], array_keys($amounts), $amounts);

        if (count($entries) <= $max) {
            return $entries;
        }

        $kept = array_slice($entries, 0, $max - 1);
        $rest = array_slice($entries, $max - 1);
        $otherTotal = array_sum(array_column($rest, 'amount'));

        $kept[] = ['label' => 'Khác', 'amount' => $otherTotal];

        return $kept;
    }

    /**
     * Chuẩn hoá dữ liệu trả ra frontend. CHỈ `password` là bí mật thật —
     * ẩn nếu viewer không phải creator/viewer được cấp riêng (bảng
     * credential_viewers). `username`/`email` là thông tin định danh "ai/
     * email nào đang dùng tài khoản dịch vụ" (khác thông tin đăng nhập hệ
     * thống VA Workspace của nhân viên) — không đủ để đăng nhập nếu thiếu
     * password, nên hiện công khai cho mọi người có quyền credential.view
     * (đáp ứng yêu cầu "phải biết tài khoản của ai, email nào"). Metadata
     * khác (provider, loại, trạng thái, server, domain...) luôn hiện đầy
     * đủ. Chi phí bị ẩn riêng theo cost_hidden (mục 14 CLAUDE.md — không
     * giữ chỗ rỗng, chỉ đơn giản không trả giá trị).
     *
     * @return array<string, mixed>
     */
    public function present(Credential $credential, User $viewer): array
    {
        $canSeeSecret = $this->canSeeSecret($credential, $viewer);
        $canManage = $this->canManage($viewer);

        return [
            'id' => $credential->id,
            'department_id' => $credential->department_id,
            'department' => $credential->department ? [
                'id' => $credential->department->id,
                'name' => $credential->department->name,
            ] : null,
            'name' => $credential->name,
            'provider' => $credential->provider ? [
                'id' => $credential->provider->id,
                'name' => $credential->provider->name,
                'category' => $credential->provider->category,
                'icon' => $credential->provider->icon,
            ] : null,
            'account_type' => $credential->account_type,
            'group' => $credential->group,
            'status' => $credential->status,
            'is_google_login' => $credential->is_google_login,
            'google_account_owner' => $credential->google_account_owner,
            'google_account_owner_id' => $credential->google_account_owner_id,
            'google_account_owner_user' => $credential->googleAccountOwner ? [
                'id' => $credential->googleAccountOwner->id,
                'name' => $credential->googleAccountOwner->name,
                'email' => $credential->googleAccountOwner->email,
            ] : null,
            'server_name' => $credential->server_name,
            'vps_cluster' => $credential->vps_cluster,
            'domain' => $credential->domain,
            'access_url' => $credential->access_url,
            'database_name' => $credential->database_name,
            'is_root_account' => $credential->is_root_account,
            'is_iam_account' => $credential->is_iam_account,
            'notes' => $credential->notes,
            'purchased_at' => optional($credential->purchased_at)->toDateString(),
            'expires_at' => optional($credential->expires_at)->toDateString(),
            'monthly_cost' => $credential->cost_hidden ? null : $credential->monthly_cost,
            'cost_hidden' => $credential->cost_hidden,
            'currency' => $credential->currency,
            'created_by' => $credential->created_by,
            'creator_name' => optional($credential->creator)->name,
            'can_see_secret' => $canSeeSecret,
            'can_manage' => $canManage,
            'can_choose_department' => $this->departmentScopeFor($viewer) === null,
            'username' => $credential->username,
            'email' => $credential->email,
            'password' => $canSeeSecret ? $credential->password : null,
            'viewers' => $canSeeSecret || $canManage
                ? $credential->viewers->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()
                : [],
        ];
    }

    /**
     * Xuất báo cáo chi phí ra file Excel (.xlsx) — 3 sheet (Tổng quan +
     * breakdown, Chi tiết từng tài khoản, Dự phóng 12 tháng) để đủ số
     * liệu ra quyết định trong 1 file, không cần đối chiếu nhiều nguồn.
     */
    public function exportCostExcel(User $exportedBy): BinaryFileResponse
    {
        $summary = $this->costSummary($exportedBy);
        $forecast = $this->costForecast($exportedBy);
        $filename = 'Bao_cao_chi_phi_tai_khoan_'.now()->format('Ymd_His').'.xlsx';

        $this->activityLogs->record(
            'credential.export_cost_excel',
            'Xuất báo cáo chi phí tài khoản ra Excel',
            $exportedBy,
        );

        return $this->excelExporter->download($summary, $forecast, $exportedBy, $filename);
    }

    /** Xuất báo cáo chi phí ra file PDF — có thêm khối tổng quan đầu trang. */
    public function exportCostPdf(User $exportedBy)
    {
        $summary = $this->costSummary($exportedBy);
        $filename = 'Bao_cao_chi_phi_tai_khoan_'.now()->format('Ymd_His').'.pdf';

        $this->activityLogs->record(
            'credential.export_cost_pdf',
            'Xuất báo cáo chi phí tài khoản ra PDF',
            $exportedBy,
        );

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('credential::pdf.cost-report', [
            'summary' => $summary,
            'rows' => $summary['items'],
            'exportedBy' => $exportedBy,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
