<?php

namespace Modules\Evaluation\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationPosition;
use Modules\Evaluation\App\Models\EvaluationTemplate;
use Modules\Evaluation\App\Models\EvaluationTemplateCriterion;
use Modules\Evaluation\App\Models\EvaluationTemplateCustomField;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationPositionRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationTemplateRepositoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Mẫu đánh giá (Evaluation Giai đoạn C). Xem plans/2026-08-26-mau-danh-gia.md.
 *
 * Ràng buộc tiêu chí (đã chốt §4 của plan):
 *   - Mẫu thường (is_global = false): chỉ chọn tiêu chí active của ĐÚNG
 *     department_id của mẫu.
 *   - Mẫu global (is_global = true): được chọn tiêu chí active của
 *     BẤT KỲ phòng ban nào.
 * Validate ở đây (tầng Service), không chỉ chặn ở UI.
 */
class EvaluationTemplateService
{
    private const CODE_PREFIX = 'EVT-';

    private const CODE_PAD = 4;

    public function __construct(
        private readonly EvaluationTemplateRepositoryInterface $templates,
        private readonly EvaluationCriteriaRepositoryInterface $criteria,
        private readonly EvaluationPositionRepositoryInterface $positions,
        private readonly EvaluationTemplateExcelExporter $exporter,
    ) {}

    /** Mẫu của phòng ban user + mọi mẫu is_global. */
    public function listVisibleForDepartment(int $departmentId): Collection
    {
        return $this->templates
            ->visibleForDepartment($departmentId)
            ->map(fn (EvaluationTemplate $t) => $this->present($t))
            ->values();
    }

    /** Toàn bộ mẫu mọi phòng ban — chỉ super_admin (workspace_config.view_all). */
    public function listAll(): Collection
    {
        return $this->templates
            ->all()
            ->map(fn (EvaluationTemplate $t) => $this->present($t))
            ->values();
    }

    /**
     * Xuất mẫu (của phòng ban + mọi mẫu is_global) ra Excel theo đúng bộ lọc
     * đang dùng ở trang danh sách. CHỈ xuất (đọc) — không có chiều nhập lại,
     * xem ghi chú ở EvaluationTemplateExcelExporter.
     *
     * @param  array{q?: string, status?: string}  $filters
     */
    public function export(int $departmentId, array $filters, ?User $exportedBy): BinaryFileResponse
    {
        $templates = $this->templates
            ->visibleForDepartment($departmentId)
            ->filter(fn (EvaluationTemplate $t) => $this->matchesFilters($t, $filters));

        $rows = $templates->map(fn (EvaluationTemplate $t) => $this->presentForExport($t))->values()->all();

        $filename = 'Mau_danh_gia_'.now()->format('Ymd_His').'.xlsx';

        return $this->exporter->download($rows, $exportedBy, $filename);
    }

    private function matchesFilters(EvaluationTemplate $template, array $filters): bool
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $haystack = mb_strtolower($template->name.' '.$template->code);
            if (! str_contains($haystack, $q)) {
                return false;
            }
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'active' && ! $template->is_active) {
            return false;
        }
        if ($status === 'inactive' && $template->is_active) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function presentForExport(EvaluationTemplate $template): array
    {
        $criteriaText = $template->templateCriteria
            ->map(function (EvaluationTemplateCriterion $tc) {
                $label = EvaluationTemplateCriterion::WEIGHT_LABELS[$tc->weight_label] ?? $tc->weight_label;

                return ($tc->criterion?->name ?? '?').': '.$label;
            })
            ->implode('; ');

        $positionsText = $template->positions->pluck('name')->implode(', ');

        $customFieldsText = $template->customFields
            ->map(function (EvaluationTemplateCustomField $f) {
                $typeLabel = match ($f->field_type) {
                    'text' => 'Chữ',
                    'number' => 'Số',
                    'select' => 'Lựa chọn',
                    'date' => 'Ngày',
                    default => $f->field_type,
                };

                return $f->label.': '.$typeLabel;
            })
            ->implode('; ');

        return [
            'code'               => $template->code,
            'name'               => $template->name,
            'description'        => $template->description ?? '',
            'department_name'    => $template->department?->name ?? '',
            'is_global_label'    => $template->is_global ? 'Có' : 'Không',
            'status_label'       => $template->is_active ? 'Hoạt động' : 'Không hoạt động',
            'criteria_count'     => $template->templateCriteria->count(),
            'criteria_text'      => $criteriaText,
            'positions_text'     => $positionsText,
            'custom_fields_text' => $customFieldsText,
            'max_score'          => $template->max_score,
            'creator_name'       => $template->creator?->name ?? '',
            'updater_name'       => $template->updater?->name ?? '',
            'created_at'         => $template->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return \Modules\Evaluation\App\Models\EvaluationTemplate|\Illuminate\Http\JsonResponse
     */
    public function findVisibleOrFail(int $id, int $departmentId)
    {
        $template = $this->templates->findVisibleForDepartment($id, $departmentId);

        if ($template === null) {
            return response()->json(['message' => 'Không tìm thấy mẫu đánh giá.'], 404);
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return \Modules\Evaluation\App\Models\EvaluationTemplate|array{error: string}
     */
    public function create(int $departmentId, int $createdBy, array $data): EvaluationTemplate|array
    {
        $rows = $this->buildCriteriaRows($data['criteria'] ?? [], $departmentId, isGlobal: false);
        if (is_array($rows) && isset($rows['error'])) {
            return $rows;
        }

        $positionIds = $this->buildPositionIds($data['position_ids'] ?? []);
        if (is_array($positionIds) && isset($positionIds['error'])) {
            return $positionIds;
        }

        $customFieldRows = $this->buildCustomFieldRows($data['custom_fields'] ?? []);
        if (isset($customFieldRows['error'])) {
            return $customFieldRows;
        }

        $template = $this->templates->create([
            'department_id' => $departmentId,
            'code'          => $this->generateCode(),
            'name'          => trim($data['name']),
            'description'   => isset($data['description']) ? trim($data['description']) : null,
            'is_global'     => false,
            'is_active'     => $data['is_active'] ?? true,
            'created_by'    => $createdBy,
            'updated_by'    => $createdBy,
        ]);

        $template = $this->templates->syncCriteria($template, $rows);
        $template = $this->templates->syncPositions($template, $positionIds);

        return $this->templates->syncCustomFields($template, $customFieldRows);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return \Modules\Evaluation\App\Models\EvaluationTemplate|array{error: string}
     */
    public function update(EvaluationTemplate $template, array $data, ?int $updatedBy = null): EvaluationTemplate|array
    {
        $payload = [
            'name'        => trim($data['name'] ?? $template->name),
            'description' => array_key_exists('description', $data)
                ? (isset($data['description']) ? trim($data['description']) : null)
                : $template->description,
            'is_active'   => $data['is_active'] ?? $template->is_active,
        ];

        if ($updatedBy !== null) {
            $payload['updated_by'] = $updatedBy;
        }

        $updated = $this->templates->update($template, $payload);

        if (array_key_exists('criteria', $data)) {
            $rows = $this->buildCriteriaRows($data['criteria'], (int) $updated->department_id, $updated->is_global);
            if (is_array($rows) && isset($rows['error'])) {
                return $rows;
            }

            $updated = $this->templates->syncCriteria($updated, $rows);
        }

        if (array_key_exists('position_ids', $data)) {
            $positionIds = $this->buildPositionIds($data['position_ids']);
            if (is_array($positionIds) && isset($positionIds['error'])) {
                return $positionIds;
            }

            $updated = $this->templates->syncPositions($updated, $positionIds);
        }

        if (array_key_exists('custom_fields', $data)) {
            $customFieldRows = $this->buildCustomFieldRows($data['custom_fields']);
            if (isset($customFieldRows['error'])) {
                return $customFieldRows;
            }

            $updated = $this->templates->syncCustomFields($updated, $customFieldRows);
        }

        return $updated;
    }

    public function toggleActive(EvaluationTemplate $template, ?int $updatedBy = null): EvaluationTemplate
    {
        return $this->templates->toggleActive($template, $updatedBy);
    }

    /**
     * Toàn bộ tiêu chí ĐANG HOẠT ĐỘNG của mọi phòng ban, kèm tên phòng ban
     * nguồn — chỉ dùng khi build mẫu is_global (PR4). Controller kiểm tra
     * quyền evaluation.manage_global_template trước khi gọi hàm này.
     *
     * @return list<array<string, mixed>>
     */
    public function listCriteriaAcrossDepartments(): array
    {
        return $this->criteria->allActiveAcrossDepartments()
            ->map(fn ($criterion) => [
                'id'         => $criterion->id,
                'name'       => $criterion->name,
                'type'       => $criterion->type,
                'levels'     => $criterion->levels ?? [],
                'max_score'  => $criterion->max_score,
                'is_active'  => (bool) $criterion->is_active,
                'department' => $criterion->department ? [
                    'id'   => $criterion->department->id,
                    'name' => $criterion->department->name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /** Bật/tắt dùng chung toàn hệ thống — Controller đã kiểm tra evaluation.manage_global_template. */
    public function toggleGlobal(EvaluationTemplate $template, ?int $updatedBy = null): EvaluationTemplate
    {
        return $this->templates->toggleGlobal($template, $updatedBy);
    }

    public function delete(EvaluationTemplate $template): bool
    {
        return $this->templates->delete($template);
    }

    /**
     * Nhân bản mẫu — tạo mẫu mới cùng phòng ban người thao tác, copy toàn bộ
     * tiêu chí + trọng số. Mẫu nhân bản luôn is_global = false (không tự
     * động kế thừa trạng thái dùng chung, tránh phát sinh mẫu chung ngoài ý muốn).
     */
    public function duplicate(EvaluationTemplate $template, int $departmentId, int $createdBy): EvaluationTemplate
    {
        $copy = $this->templates->create([
            'department_id' => $departmentId,
            'code'          => $this->generateCode(),
            'name'          => $template->name.' (bản sao)',
            'description'   => $template->description,
            'is_global'     => false,
            'is_active'     => true,
            'created_by'    => $createdBy,
            'updated_by'    => $createdBy,
        ]);

        $rows = $template->templateCriteria
            ->map(fn (EvaluationTemplateCriterion $tc) => [
                'evaluation_criteria_id' => $tc->evaluation_criteria_id,
                'weight_label'           => $tc->weight_label,
                'weight_value'           => $tc->weight_value,
                'required_score'         => $tc->required_score,
                'count_in_total'         => $tc->count_in_total,
            ])
            ->all();

        $copy = $this->templates->syncCriteria($copy, $rows);

        $positionIds = $template->positions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $copy = $this->templates->syncPositions($copy, $positionIds);

        $customFieldRows = $template->customFields
            ->map(fn (EvaluationTemplateCustomField $f) => [
                'label'       => $f->label,
                'field_type'  => $f->field_type,
                'options'     => $f->options,
                'is_required' => $f->is_required,
            ])
            ->all();

        return $this->templates->syncCustomFields($copy, $customFieldRows);
    }

    /**
     * Chuẩn hoá + validate danh sách tiêu chí gửi lên.
     *
     * @param  array<int, array<string, mixed>>  $rawRows
     * @return list<array{evaluation_criteria_id: int, weight_label: string, weight_value: int, required_score: int|null, count_in_total: bool}>|array{error: string}
     */
    private function buildCriteriaRows(array $rawRows, int $templateDepartmentId, bool $isGlobal): array
    {
        $rows = [];

        foreach ($rawRows as $raw) {
            $criterionId = (int) ($raw['evaluation_criteria_id'] ?? 0);
            $criterion = $this->criteria->find($criterionId);

            if ($criterion === null || ! $criterion->is_active) {
                return ['error' => 'Tiêu chí đánh giá không hợp lệ hoặc đã ngừng áp dụng.'];
            }

            if (! $isGlobal && (int) $criterion->department_id !== $templateDepartmentId) {
                return ['error' => 'Chỉ được chọn tiêu chí thuộc phòng ban của mẫu này.'];
            }

            $weightLabel = (string) ($raw['weight_label'] ?? 'quan_trong');
            $weightValue = EvaluationTemplateCriterion::WEIGHT_MAP[$weightLabel]
                ?? EvaluationTemplateCriterion::WEIGHT_MAP['quan_trong'];

            $rows[] = [
                'evaluation_criteria_id' => $criterionId,
                'weight_label'           => $weightLabel,
                'weight_value'           => $weightValue,
                'required_score'         => isset($raw['required_score']) ? (int) $raw['required_score'] : null,
                'count_in_total'         => (bool) ($raw['count_in_total'] ?? true),
            ];
        }

        return $rows;
    }

    /**
     * Validate danh sách vị trí đánh giá gửi lên — tất cả id phải tồn tại
     * trong danh mục dùng chung (evaluation_positions).
     *
     * @param  array<int, mixed>  $rawIds
     * @return list<int>|array{error: string}
     */
    private function buildPositionIds(array $rawIds): array
    {
        if (empty($rawIds)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $rawIds)));
        $found = $this->positions->findMany($ids);

        if ($found->count() !== count($ids)) {
            return ['error' => 'Có vị trí đánh giá không hợp lệ trong danh sách đã chọn.'];
        }

        return $ids;
    }

    /**
     * Chuẩn hoá + validate danh sách trường tùy biến gửi lên (PR5). Chỉ lưu
     * định nghĩa field, không có giá trị thật (chờ phiếu Giai đoạn D).
     *
     * @param  array<int, array<string, mixed>>  $rawRows
     * @return list<array{label: string, field_type: string, options: list<string>|null, is_required: bool}>|array{error: string}
     */
    private function buildCustomFieldRows(array $rawRows): array
    {
        $rows = [];

        foreach ($rawRows as $raw) {
            $label = trim((string) ($raw['label'] ?? ''));
            if ($label === '') {
                return ['error' => 'Trường tùy biến phải có nhãn hiển thị.'];
            }

            $fieldType = (string) ($raw['field_type'] ?? 'text');
            if (! in_array($fieldType, EvaluationTemplateCustomField::FIELD_TYPES, true)) {
                return ['error' => 'Loại trường tùy biến không hợp lệ.'];
            }

            $options = null;
            if ($fieldType === 'select') {
                $options = collect($raw['options'] ?? [])
                    ->map(fn ($opt) => trim((string) $opt))
                    ->filter(fn ($opt) => $opt !== '')
                    ->values()
                    ->all();

                if (empty($options)) {
                    return ['error' => 'Trường tùy biến kiểu lựa chọn phải có ít nhất 1 tùy chọn.'];
                }
            }

            $rows[] = [
                'label'       => $label,
                'field_type'  => $fieldType,
                'options'     => $options,
                'is_required' => (bool) ($raw['is_required'] ?? false),
            ];
        }

        return $rows;
    }

    private function generateCode(): string
    {
        $sequence = $this->templates->nextCodeSequence();

        return self::CODE_PREFIX.str_pad((string) $sequence, self::CODE_PAD, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    public function present(EvaluationTemplate $template): array
    {
        $department = $template->department;

        return [
            'id'          => $template->id,
            'code'        => $template->code,
            'name'        => $template->name,
            'description' => $template->description,
            'is_global'   => (bool) $template->is_global,
            'is_active'   => (bool) $template->is_active,
            'department'  => $department ? [
                'id'   => $department->id,
                'name' => $department->name,
            ] : null,
            'criteria_count' => $template->templateCriteria->count(),
            'max_score'       => $template->max_score,
            'criteria'        => $template->templateCriteria
                ->map(fn (EvaluationTemplateCriterion $tc) => $this->presentTemplateCriterion($tc))
                ->values()
                ->all(),
            'positions' => $template->positions
                ->map(fn (EvaluationPosition $p) => ['id' => $p->id, 'name' => $p->name, 'kind' => $p->kind])
                ->values()
                ->all(),
            'custom_fields' => $template->customFields
                ->map(fn (EvaluationTemplateCustomField $f) => [
                    'id'          => $f->id,
                    'label'       => $f->label,
                    'field_type'  => $f->field_type,
                    'options'     => $f->options,
                    'is_required' => (bool) $f->is_required,
                ])
                ->values()
                ->all(),
            'created_by' => $template->created_by,
            'updated_by' => $template->updated_by,
            'creator'    => $this->presentUser($template->creator),
            'updater'    => $this->presentUser($template->updater),
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentTemplateCriterion(EvaluationTemplateCriterion $tc): array
    {
        $criterion = $tc->criterion;

        return [
            'id'                     => $tc->id,
            'evaluation_criteria_id' => $tc->evaluation_criteria_id,
            'name'                   => $criterion?->name,
            'criterion_type'         => $criterion?->criterionType ? [
                'id'   => $criterion->criterionType->id,
                'name' => $criterion->criterionType->name,
            ] : null,
            // Tên phòng ban nguồn của tiêu chí — cần khi mẫu global trộn tiêu chí
            // nhiều phòng ban (xem plans/2026-08-26-mau-danh-gia.md §4, §6.2).
            'department' => $criterion?->department ? [
                'id'   => $criterion->department->id,
                'name' => $criterion->department->name,
            ] : null,
            'weight_label'   => $tc->weight_label,
            'weight_text'    => EvaluationTemplateCriterion::WEIGHT_LABELS[$tc->weight_label] ?? $tc->weight_label,
            'required_score' => $tc->required_score,
            'count_in_total' => (bool) $tc->count_in_total,
            'max_score'      => $criterion?->max_score,
        ];
    }

    /** @return array{id: int, name: string, email: string|null, avatar_url: string|null, department: array{id: int, name: string}|null}|null */
    private function presentUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $department = $user->department;

        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'avatar_url' => $user->avatar_url,
            'department' => $department ? [
                'id'   => $department->id,
                'name' => $department->name,
            ] : null,
        ];
    }
}
