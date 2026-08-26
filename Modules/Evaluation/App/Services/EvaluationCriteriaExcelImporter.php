<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Http\UploadedFile;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriterionTypeRepositoryInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Đọc file Excel (.xlsx) theo đúng cấu trúc cột của
 * EvaluationCriteriaExcelExporter::HEADERS và trả về bảng xem trước (preview)
 * cho TỪNG dòng: dữ liệu đã chuẩn hoá + toàn bộ vấn đề phát hiện được
 * (khoảng trắng thừa, trùng tên trong file, trùng tên với DB, thiếu trường,
 * sai định dạng...) — KHÔNG ghi DB. EvaluationCriteriaService::confirmImport()
 * lo việc ghi thật từ những dòng người dùng đã xác nhận.
 *
 * Khác với bản đọc cũ (dừng ở lỗi đầu tiên của mỗi dòng), preview() luôn đi
 * hết các bước kiểm tra để gom đủ TẤT CẢ vấn đề của 1 dòng, giúp người dùng
 * thấy toàn bộ lý do cùng lúc thay vì sửa từng lỗi một qua nhiều lần thử.
 */
class EvaluationCriteriaExcelImporter
{
    // Cột theo đúng thứ tự EvaluationCriteriaExcelExporter::HEADERS (1-based).
    private const COL_TYPE_CODE = 2;

    private const COL_NAME = 4;

    private const COL_SCORING = 5;

    private const COL_DESCRIPTION = 6;

    private const COL_LEVELS = 7;

    private const COL_ALLOW_HALF = 9;

    private const COL_STATUS = 10;

    private const COL_USE_IN_EVALUATION = 11;

    private const COL_SORT_ORDER = 12;

    private const SCORING_LABELS = ['Thang điểm' => 'scale', 'Cộng/trừ' => 'behavior'];

    /** Loại issue nào chặn không cho nhập dòng đó — issue ngoài danh sách này (vd whitespace) chỉ là ghi chú. */
    private const BLOCKING_ISSUE_TYPES = [
        'missing_field',
        'invalid_type_code',
        'invalid_scoring',
        'invalid_levels',
        'duplicate_in_file',
        'duplicate_in_db',
    ];

    public function __construct(
        private readonly EvaluationCriterionTypeRepositoryInterface $types,
        private readonly EvaluationCriteriaRepositoryInterface $criteria,
    ) {}

    /**
     * @return array{rows: list<array{row:int, status:string, data:array<string,mixed>, issues:list<array{type:string,message:string}>}>}
     */
    public function preview(UploadedFile $file, int $departmentId): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Tiêu chí đánh giá') ?? $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        $rows = [];
        $typeCache = [];
        $seenNames = []; // name chuẩn hoá (lowercase) => số dòng Excel đầu tiên gặp, phát hiện trùng TRONG FILE
        $existingNames = $this->criteria->namesByDepartment($departmentId);

        for ($excelRow = 5; $excelRow <= $highestRow; $excelRow++) {
            $issues = [];

            $rawName = (string) $this->cellValue($sheet, self::COL_NAME, $excelRow);
            $name = $this->normalizeWhitespace($rawName);
            if ($rawName !== '' && $rawName !== $name) {
                $issues[] = ['type' => 'whitespace', 'message' => 'Tên đã được tự động chuẩn hoá khoảng trắng.'];
            }

            $rawDescription = (string) $this->cellValue($sheet, self::COL_DESCRIPTION, $excelRow);
            $description = $this->normalizeWhitespace($rawDescription);
            if ($rawDescription !== '' && $rawDescription !== $description) {
                $issues[] = ['type' => 'whitespace', 'message' => 'Mô tả đã được tự động chuẩn hoá khoảng trắng.'];
            }

            $typeCode = trim((string) $this->cellValue($sheet, self::COL_TYPE_CODE, $excelRow));

            // Dòng trống hoàn toàn — bỏ qua, không tính là 1 dòng cần xem xét.
            if ($name === '' && $typeCode === '') {
                continue;
            }

            if ($name === '') {
                $issues[] = ['type' => 'missing_field', 'message' => 'Thiếu tên tiêu chí.'];
            }

            $type = null;
            if ($typeCode === '') {
                $issues[] = ['type' => 'missing_field', 'message' => 'Thiếu mã loại tiêu chí.'];
            } else {
                if (! array_key_exists($typeCode, $typeCache)) {
                    $typeCache[$typeCode] = $this->types->findByCode($departmentId, $typeCode);
                }
                $type = $typeCache[$typeCode];
                if ($type === null) {
                    $issues[] = [
                        'type' => 'invalid_type_code',
                        'message' => "Không tìm thấy loại tiêu chí mã \"{$typeCode}\". Hãy tạo loại tiêu chí này trước.",
                    ];
                }
            }

            $scoringLabel = trim((string) $this->cellValue($sheet, self::COL_SCORING, $excelRow));
            $scoringType = self::SCORING_LABELS[$scoringLabel] ?? null;
            if ($scoringType === null) {
                $issues[] = [
                    'type' => 'invalid_scoring',
                    'message' => 'Cột "Cách chấm" phải là "Thang điểm" hoặc "Cộng/trừ".',
                ];
            }

            $levelsText = trim((string) $this->cellValue($sheet, self::COL_LEVELS, $excelRow));
            $levels = $this->parseLevels($levelsText);
            if (count($levels) === 0) {
                $issues[] = [
                    'type' => 'invalid_levels',
                    'message' => 'Cột "Các mức" trống hoặc sai định dạng (mẫu: Tốt: 5; Khá: 3).',
                ];
            }

            $nameKey = mb_strtolower($name);
            if ($name !== '') {
                if (isset($seenNames[$nameKey])) {
                    $issues[] = [
                        'type' => 'duplicate_in_file',
                        'message' => "Trùng tên với dòng {$seenNames[$nameKey]} trong cùng file.",
                    ];
                } else {
                    $seenNames[$nameKey] = $excelRow;
                }
                if (in_array($nameKey, $existingNames, true)) {
                    $issues[] = [
                        'type' => 'duplicate_in_db',
                        'message' => 'Tên tiêu chí đã được dùng trong phòng ban này.',
                    ];
                }
            }

            $allowHalfLabel = trim((string) $this->cellValue($sheet, self::COL_ALLOW_HALF, $excelRow));
            $allowHalf = mb_strtolower($allowHalfLabel) === 'có';
            $statusLabel = trim((string) $this->cellValue($sheet, self::COL_STATUS, $excelRow));
            $useInEvalLabel = trim((string) $this->cellValue($sheet, self::COL_USE_IN_EVALUATION, $excelRow));
            $sortOrder = (int) $this->cellValue($sheet, self::COL_SORT_ORDER, $excelRow);

            $isValid = count(array_intersect(
                self::BLOCKING_ISSUE_TYPES,
                array_column($issues, 'type'),
            )) === 0;

            $rows[] = [
                'row' => $excelRow,
                'status' => $isValid ? 'valid' : 'invalid',
                'data' => [
                    'type_code' => $typeCode,
                    'criterion_type_id' => $type?->id,
                    'name' => $name,
                    'type' => $scoringType ?? 'scale',
                    'description' => $description !== '' ? $description : null,
                    'levels' => $levels,
                    'is_active' => mb_strtolower($statusLabel) !== 'ngừng áp dụng',
                    'allow_half' => $allowHalf,
                    'use_in_evaluation' => mb_strtolower($useInEvalLabel) !== 'không',
                    'sort_order' => max($sortOrder, 0),
                ],
                'issues' => $issues,
            ];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return ['rows' => $rows];
    }

    /** PhpSpreadsheet 2.0 không còn getCellByColumnAndRow() — dùng getCell([col, row]). */
    private function cellValue(Worksheet $sheet, int $col, int $row): mixed
    {
        return $sheet->getCell([$col, $row])->getValue();
    }

    /** Trim 2 đầu + gộp mọi chuỗi khoảng trắng liên tiếp (kể cả tab/xuống dòng) thành 1 dấu cách. */
    private function normalizeWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Parse chuỗi "Tốt: 5; Khá: 3; Yếu: 1" -> mảng level, tự sinh code M1, M2…
     *
     * @return array<array{code: string, label: string, description: string, score: float}>
     */
    private function parseLevels(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $levels = [];
        $index = 0;
        foreach (explode(';', $text) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || ! str_contains($chunk, ':')) {
                continue;
            }
            [$label, $scoreRaw] = array_map('trim', explode(':', $chunk, 2));
            if ($label === '' || ! is_numeric($scoreRaw)) {
                continue;
            }
            $index++;
            $levels[] = [
                'code' => 'M'.$index,
                'label' => $label,
                'description' => '',
                'score' => (float) $scoreRaw,
            ];
        }

        return $levels;
    }
}
