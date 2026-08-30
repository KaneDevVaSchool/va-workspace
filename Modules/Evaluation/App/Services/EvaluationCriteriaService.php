<?php

namespace Modules\Evaluation\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriterionTypeRepositoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvaluationCriteriaService
{
    public function __construct(
        private readonly EvaluationCriteriaRepositoryInterface $criteria,
        private readonly EvaluationCriterionTypeRepositoryInterface $types,
        private readonly EvaluationCriteriaExcelExporter $exporter,
        private readonly EvaluationCriteriaExcelImporter $importer,
    ) {}

    /** Danh sách tiêu chí của phòng ban, đã present sẵn cho API response. */
    public function listForDepartment(int $departmentId): Collection
    {
        return $this->criteria
            ->allByDepartment($departmentId)
            ->map(fn (EvaluationCriteria $c) => $this->present($c))
            ->values();
    }

    /** @return list<int> */
    public function idsForDepartment(int $departmentId): array
    {
        return $this->criteria->idsByDepartment($departmentId);
    }

    /**
     * Xuất tiêu chí của phòng ban ra file Excel, áp dụng đúng bộ lọc (q/kind/type/status)
     * đang được dùng ở trang danh sách — cùng tiêu chí lọc với computed `filtered` phía frontend.
     *
     * @param  array{q?: string, kind?: string, type?: string, status?: string}  $filters
     */
    public function export(int $departmentId, array $filters, ?User $exportedBy): BinaryFileResponse
    {
        $criteria = $this->criteria->allByDepartment($departmentId)->filter(
            fn (EvaluationCriteria $c) => $this->matchesFilters($c, $filters),
        );

        $rows = $criteria->map(fn (EvaluationCriteria $c) => $this->presentForExport($c))->values()->all();

        $filename = 'Tieu_chi_danh_gia_'.now()->format('Ymd_His').'.xlsx';

        return $this->exporter->download($rows, $exportedBy, $filename);
    }

    /**
     * Xuất tiêu chí của phòng ban ra file PDF, cùng bộ lọc và cùng dữ liệu với export Excel.
     *
     * @param  array{q?: string, kind?: string, type?: string, status?: string}  $filters
     */
    public function exportPdf(int $departmentId, array $filters, ?User $exportedBy)
    {
        $criteria = $this->criteria->allByDepartment($departmentId)->filter(
            fn (EvaluationCriteria $c) => $this->matchesFilters($c, $filters),
        );

        $rows = $criteria->map(fn (EvaluationCriteria $c) => $this->presentForExport($c))->values()->all();

        $filename = 'Tieu_chi_danh_gia_'.now()->format('Ymd_His').'.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('evaluation::pdf.criteria', [
            'rows' => $rows,
            'exportedBy' => $exportedBy,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        $watermarkPath = $this->makePdfWatermarkPng();
        try {
            $this->stampPdfWatermark($pdf, $watermarkPath);
        } finally {
            if ($watermarkPath !== null && is_file($watermarkPath)) {
                @unlink($watermarkPath);
            }
        }

        return $pdf->download($filename);
    }

    /**
     * Đọc + xem trước file Excel: chuẩn hoá dữ liệu, phát hiện đầy đủ vấn đề của
     * từng dòng (khoảng trắng, trùng lặp, thiếu trường...), KHÔNG ghi DB.
     *
     * @return array{rows: list<array<string, mixed>>}
     */
    public function previewImport(int $departmentId, UploadedFile $file): array
    {
        return $this->importer->preview($file, $departmentId);
    }

    /**
     * Ghi DB các dòng đã được xác nhận từ bước preview — KHÔNG đọc lại file.
     * Tạo được dòng nào lưu dòng đó — 1 dòng lỗi không làm rollback các dòng khác.
     *
     * @param  list<array<string, mixed>>  $validatedRows  Dữ liệu đã đúng định dạng từ bước preview
     *         (chỉ những dòng frontend gửi lên là đã chọn xác nhận).
     * @return array{created: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    public function confirmImport(int $departmentId, int $importedBy, array $validatedRows): array
    {
        $created = [];
        $errors = [];

        foreach ($validatedRows as $row) {
            try {
                $criterion = DB::transaction(fn () => $this->create($departmentId, $importedBy, $row));
                $created[] = $this->present($criterion);
            } catch (\Throwable $e) {
                $errors[] = ['row' => $row['row'] ?? 0, 'message' => 'Không tạo được: '.$e->getMessage()];
            }
        }

        usort($errors, fn ($a, $b) => $a['row'] <=> $b['row']);

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Nhuộm mark trắng (nền trong suốt) sang màu brand để hiện được trên giấy trắng.
     * Trả về đường dẫn PNG tạm — caller phải xoá file sau khi render PDF.
     */
    private function makePdfWatermarkPng(): ?string
    {
        $srcPath = public_path('images/congnghe/brand/vas-white-mark@2x.png');
        if (! is_file($srcPath)) {
            $srcPath = public_path('images/congnghe/brand/vas-white-mark.png');
        }
        if (! is_file($srcPath) || ! function_exists('imagecreatefrompng')) {
            return null;
        }

        $src = @imagecreatefrompng($srcPath);
        if ($src === false) {
            return null;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $width, $height, $clear);

        // Header PDF / primary-900. Alpha nướng sẵn (~10%) — không phụ thuộc
        // set_opacity của DomPDF (không luôn áp được lên ảnh).
        $brandR = 0x9A;
        $brandG = 0x00;
        $brandB = 0x36;
        $fade = 0.10;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($src, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha >= 126) {
                    continue;
                }
                $srcOpacity = 1 - ($alpha / 127);
                $newAlpha = (int) round(127 * (1 - ($srcOpacity * $fade)));
                $newAlpha = max(0, min(127, $newAlpha));
                $color = imagecolorallocatealpha($dst, $brandR, $brandG, $brandB, $newAlpha);
                imagesetpixel($dst, $x, $y, $color);
            }
        }

        imagedestroy($src);

        $tmp = tempnam(sys_get_temp_dir(), 'vas-wm-');
        if ($tmp === false) {
            imagedestroy($dst);

            return null;
        }
        $pngPath = $tmp.'.png';
        @unlink($tmp);
        $ok = imagepng($dst, $pngPath);
        imagedestroy($dst);

        if (! $ok || ! is_file($pngPath)) {
            return null;
        }

        return $pngPath;
    }

    /**
     * Đóng dấu mark mờ lên mọi trang, vẽ sau nội dung để hiện xuyên qua bảng.
     *
     * @param  \Barryvdh\DomPDF\PDF  $pdf
     */
    private function stampPdfWatermark($pdf, ?string $watermarkPath): void
    {
        if ($watermarkPath === null) {
            return;
        }

        $pdf->render();
        $canvas = $pdf->getCanvas();
        $pageW = $canvas->get_width();
        $pageH = $canvas->get_height();
        $size = min($pageW, $pageH) * 0.58;
        $x = ($pageW - $size) / 2;
        $y = ($pageH - $size) / 2;

        $canvas->page_script(function ($pageNumber, $pageCount, $canvas) use ($watermarkPath, $x, $y, $size) {
            $canvas->image($watermarkPath, $x, $y, $size, $size);
        });
    }

    private function matchesFilters(EvaluationCriteria $criterion, array $filters): bool
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $haystack = mb_strtolower(
                $criterion->name.' '.($criterion->criterionType?->name ?? '').' '.($criterion->criterionType?->code ?? ''),
            );
            if (! str_contains($haystack, $q)) {
                return false;
            }
        }

        $kind = trim((string) ($filters['kind'] ?? ''));
        if ($kind !== '' && (string) $criterion->criterion_type_id !== $kind) {
            return false;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '' && $criterion->type !== $type) {
            return false;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'active' && ! $criterion->is_active) {
            return false;
        }
        if ($status === 'inactive' && $criterion->is_active) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function presentForExport(EvaluationCriteria $criterion): array
    {
        $levelsText = collect($criterion->levels ?? [])
            ->map(fn ($l) => ($l['label'] ?? '').': '.$this->formatScore((float) ($l['score'] ?? 0)))
            ->implode('; ');

        return [
            'type_code' => $criterion->criterionType?->code ?? '',
            'type_name' => $criterion->criterionType?->name ?? '',
            'name' => $criterion->name,
            'type_label' => $criterion->type === 'behavior' ? 'Cộng/trừ' : 'Thang điểm',
            'description' => $criterion->description ?? '',
            'levels_text' => $levelsText,
            'max_score' => $this->formatScore($criterion->max_score),
            'allow_half' => $criterion->allow_half ? 'Có' : 'Không',
            'status_label' => $criterion->is_active ? 'Đang áp dụng' : 'Ngừng áp dụng',
            'use_in_evaluation_label' => $criterion->use_in_evaluation ? 'Có' : 'Không',
            'sort_order' => $criterion->sort_order,
            'creator_name' => $criterion->creator?->name ?? '',
            'updater_name' => $criterion->updater?->name ?? '',
            'created_at' => $criterion->created_at?->toIso8601String(),
        ];
    }

    private function formatScore(float $score): string
    {
        return fmod($score, 1.0) === 0.0 ? (string) (int) $score : rtrim(rtrim(number_format($score, 1), '0'), '.');
    }

    public function create(int $departmentId, int $createdBy, array $data): EvaluationCriteria
    {
        $allowHalf = (bool) ($data['allow_half'] ?? false);
        $normalized = $this->normalizeLevels($data['type'], $data['levels'] ?? [], $allowHalf);

        $criterion = $this->criteria->create([
            'department_id'     => $departmentId,
            'criterion_type_id' => $this->resolveTypeId($departmentId, $data['criterion_type_id'] ?? null),
            'name'              => trim($data['name']),
            'type'              => $data['type'],
            'description'       => isset($data['description']) ? trim($data['description']) : null,
            'levels'            => $normalized,
            'is_active'           => $data['is_active'] ?? true,
            'allow_half'          => $allowHalf,
            'use_in_evaluation'   => $data['use_in_evaluation'] ?? true,
            'use_for_task_type'   => false,
            'sort_order'          => $data['sort_order'] ?? 0,
            'created_by'          => $createdBy,
            'updated_by'          => $createdBy,
        ]);

        if (! empty($data['use_for_task_type'])) {
            return $this->criteria->assignUseForTaskType($criterion, true, $createdBy);
        }

        return $criterion;
    }

    public function update(
        EvaluationCriteria $criterion,
        array $data,
        ?int $updatedBy = null,
    ): EvaluationCriteria {
        $type = $data['type'] ?? $criterion->type;
        $allowHalf = array_key_exists('allow_half', $data)
            ? (bool) $data['allow_half']
            : (bool) $criterion->allow_half;
        $normalized = $this->normalizeLevels(
            $type,
            $data['levels'] ?? $criterion->levels,
            $allowHalf,
        );

        $payload = [
            'name'        => trim($data['name'] ?? $criterion->name),
            'type'        => $type,
            'description' => array_key_exists('description', $data)
                ? (isset($data['description']) ? trim($data['description']) : null)
                : $criterion->description,
            'levels'             => $normalized,
            'is_active'          => $data['is_active'] ?? $criterion->is_active,
            'allow_half'         => $allowHalf,
            'use_in_evaluation'  => array_key_exists('use_in_evaluation', $data)
                ? (bool) $data['use_in_evaluation']
                : (bool) $criterion->use_in_evaluation,
            'sort_order'         => $data['sort_order'] ?? $criterion->sort_order,
        ];

        if ($updatedBy !== null) {
            $payload['updated_by'] = $updatedBy;
        }

        if (array_key_exists('criterion_type_id', $data)) {
            $payload['criterion_type_id'] = $this->resolveTypeId(
                (int) $criterion->department_id,
                $data['criterion_type_id'],
            );
        }

        $updated = $this->criteria->update($criterion, $payload);

        if (array_key_exists('use_for_task_type', $data)) {
            return $this->criteria->assignUseForTaskType(
                $updated,
                (bool) $data['use_for_task_type'],
                $updatedBy,
            );
        }

        return $updated;
    }

    public function toggleActive(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria
    {
        return $this->criteria->toggleActive($criterion, $updatedBy);
    }

    public function toggleUseInEvaluation(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria
    {
        return $this->criteria->toggleUseInEvaluation($criterion, $updatedBy);
    }

    public function toggleUseForTaskType(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria
    {
        return $this->criteria->assignUseForTaskType(
            $criterion,
            ! $criterion->use_for_task_type,
            $updatedBy,
        );
    }

    public function delete(EvaluationCriteria $criterion): bool
    {
        return $this->criteria->delete($criterion);
    }

    /**
     * Tìm tiêu chí theo id + phòng ban, trả 404 JsonResponse nếu không tìm thấy.
     * Controller dùng hàm này để tránh lặp pattern kiểm tra null.
     *
     * @return \Modules\Evaluation\App\Models\EvaluationCriteria|\Illuminate\Http\JsonResponse
     */
    public function findByDepartmentOrFail(int $id, int $departmentId)
    {
        $criterion = $this->criteria->findByDepartment($id, $departmentId);

        if ($criterion === null) {
            return response()->json(['message' => 'Không tìm thấy tiêu chí đánh giá.'], 404);
        }

        return $criterion;
    }

    public function reorder(int $departmentId, array $orderedIds): void
    {
        $this->criteria->reorder($departmentId, $orderedIds);
    }

    /** Trả về mảng present cho JSON response. */
    public function present(EvaluationCriteria $criterion): array
    {
        $levels = $criterion->levels ?? [];

        $type = $criterion->criterionType;

        return [
            'id'                 => $criterion->id,
            'criterion_type_id'  => $criterion->criterion_type_id,
            'criterion_type'     => $type ? [
                'id'          => $type->id,
                'name'        => $type->name,
                'code'        => $type->code,
                'description' => $type->description,
            ] : null,
            'name'        => $criterion->name,
            'type'        => $criterion->type,
            'description' => $criterion->description,
            'levels'      => $levels,
            'level_count' => count($levels),
            'max_score'          => $criterion->max_score,
            // Phòng ban nguồn của tiêu chí — cần khi picker mẫu đánh giá gộp
            // tiêu chí nhiều phòng ban (mẫu is_global) để phân biệt rõ nguồn.
            'department' => $criterion->department ? [
                'id'   => $criterion->department->id,
                'name' => $criterion->department->name,
            ] : null,
            'is_active'          => $criterion->is_active,
            'allow_half'         => (bool) $criterion->allow_half,
            'use_in_evaluation'  => (bool) $criterion->use_in_evaluation,
            'use_for_task_type'  => (bool) $criterion->use_for_task_type,
            'sort_order'         => $criterion->sort_order,
            'created_by'         => $criterion->created_by,
            'updated_by'         => $criterion->updated_by,
            'creator'            => $this->presentUser($criterion->creator),
            'updater'            => $this->presentUser($criterion->updater),
            'created_at'         => $criterion->created_at?->toIso8601String(),
            'updated_at'         => $criterion->updated_at?->toIso8601String(),
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

    private function resolveTypeId(int $departmentId, mixed $typeId): ?int
    {
        if ($typeId === null || $typeId === '') {
            return null;
        }

        $id = (int) $typeId;
        if ($id < 1) {
            return null;
        }

        return $this->types->findByDepartment($id, $departmentId)?->id;
    }

    /**
     * Chuẩn hoá mảng levels trước khi lưu:
     * - Loại bỏ mức trống.
     * - Scale: score dương, bước 1 hoặc 0.5 tuỳ allow_half.
     * - Behavior: score khác 0, có thể âm, cùng bước.
     *
     * @param  array<array{code?: string, label: string, description?: string, score: float|int}>  $levels
     * @return array<array{code: string, label: string, description: string, score: float}>
     */
    private function normalizeLevels(string $type, array $levels, bool $allowHalf = false): array
    {
        $result = [];
        $minScale = $allowHalf ? 0.5 : 1.0;

        foreach ($levels as $level) {
            $label = trim((string) ($level['label'] ?? ''));
            $raw = (float) ($level['score'] ?? 0);
            $score = $allowHalf ? round($raw * 2) / 2 : (float) round($raw);

            if ($label === '') {
                continue;
            }

            if ($type === 'scale' && $score < $minScale) {
                $score = $minScale;
            }

            if ($type === 'behavior' && $score == 0.0) {
                continue;
            }

            $result[] = [
                'code'        => strtoupper(trim((string) ($level['code'] ?? ''))),
                'label'       => $label,
                'description' => trim((string) ($level['description'] ?? '')),
                'score'       => $score,
            ];
        }

        return $result;
    }
}
