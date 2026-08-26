<?php

namespace Modules\Evaluation\App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Xuất danh sách tiêu chí đánh giá ra file Excel (.xlsx).
 * Cùng khuôn mẫu với Modules\Identity\App\Services\ActivityLogExcelExporter —
 * dòng 1 tiêu đề lớn, dòng 2 ghi chú, dòng 4 header cột, từ dòng 5 dữ liệu.
 *
 * File xuất ra cũng được dùng làm "file mẫu" cho chức năng Nhập từ Excel
 * (import) — vì vậy thứ tự + tên cột ở đây phải khớp với
 * EvaluationCriteriaExcelImporter::COLUMN_* .
 */
class EvaluationCriteriaExcelExporter
{
    private const PRIMARY = '9A0036';

    private const PRIMARY_SOFT = 'FFE0E4';

    private const HEADER_TEXT = 'FFFFFF';

    private const ZEBRA = 'F7F7F8';

    private const BORDER = 'E5E5E8';

    private const BORDER_OUTER = 'C9C9CE';

    private const STATUS_ACTIVE_BG = 'E7F7EE';

    private const STATUS_INACTIVE_BG = 'FBEAEA';

    private const TEXT = '1A1A1A';

    public const HEADER_ROW = 4;

    public const HEADERS = [
        'STT',
        'Mã loại tiêu chí',
        'Tên loại tiêu chí',
        'Tên tiêu chí',
        'Cách chấm',
        'Mô tả',
        'Các mức (label: điểm; …)',
        'Điểm tối đa',
        'Cho phép 0.5 điểm',
        'Trạng thái',
        'Hiện trên đánh giá',
        'Thứ tự',
        'Người tạo',
        'Người cập nhật',
        'Ngày tạo',
    ];

    /** @param  list<array<string, mixed>>  $rows */
    public function download(
        array $rows,
        ?User $exportedBy,
        string $filename,
    ): BinaryFileResponse {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)
            ->setColor(new Color(self::TEXT));

        $this->fillDataSheet($spreadsheet->getActiveSheet(), $rows, $exportedBy);

        $path = tempnam(sys_get_temp_dir(), 'eval_criteria_xlsx_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function fillDataSheet(Worksheet $sheet, array $rows, ?User $exportedBy): void
    {
        $sheet->setTitle('Tiêu chí đánh giá');
        $headers = self::HEADERS;
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRow = self::HEADER_ROW;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'TIÊU CHÍ ĐÁNH GIÁ');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => self::HEADER_TEXT],
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::PRIMARY],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $note = sprintf(
            'Xuất lúc %s bởi %s · %s dòng',
            now()->format('d/m/Y H:i'),
            $exportedBy?->name ?: 'Hệ thống',
            number_format(count($rows), 0, ',', '.'),
        );
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', $note);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => self::PRIMARY], 'name' => 'Calibri'],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::PRIMARY_SOFT],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        foreach ($headers as $index => $label) {
            $sheet->setCellValue([$index + 1, $headerRow], $label);
        }

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => self::HEADER_TEXT],
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::PRIMARY],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        // Sắp theo loại tiêu chí để các dòng cùng loại nằm liên tiếp — cần thiết
        // để merge ô "Mã loại"/"Tên loại" theo nhóm ngay dưới đây.
        $sorted = $rows;
        usort($sorted, fn ($a, $b) => strcmp((string) ($a['type_code'] ?? ''), (string) ($b['type_code'] ?? '')));

        $groupStartRow = null;
        $groupTypeCode = null;

        foreach ($sorted as $i => $row) {
            $excelRow = $headerRow + 1 + $i;
            $created = $row['created_at'] ?? null;
            $carbon = $created ? Carbon::parse($created) : null;
            $typeCode = (string) ($row['type_code'] ?? '');

            $values = [
                $i + 1,
                $row['type_code'] ?? '',
                $row['type_name'] ?? '',
                $row['name'] ?? '',
                $row['type_label'] ?? '',
                $row['description'] ?? '',
                $row['levels_text'] ?? '',
                $row['max_score'] ?? '',
                $row['allow_half'] ?? '',
                $row['status_label'] ?? '',
                $row['use_in_evaluation_label'] ?? '',
                $row['sort_order'] ?? 0,
                $row['creator_name'] ?? '',
                $row['updater_name'] ?? '',
                $carbon?->format('d/m/Y H:i') ?? '',
            ];

            foreach ($values as $col => $value) {
                $sheet->setCellValue([$col + 1, $excelRow], $value);
            }

            $rowStyle = [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ];
            if ($i % 2 === 1) {
                $rowStyle['fill'] = [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => self::ZEBRA],
                ];
            }
            $sheet->getStyle("A{$excelRow}:{$lastCol}{$excelRow}")->applyFromArray($rowStyle);

            // Nền nhạt theo trạng thái, đè lên zebra ở đúng ô "Trạng thái" (cột J).
            $sheet->getStyle("J{$excelRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => ($row['status_label'] ?? '') === 'Đang áp dụng' ? self::STATUS_ACTIVE_BG : self::STATUS_INACTIVE_BG],
                ],
            ]);

            // Điểm tối đa (cột H): căn phải, in đậm nhẹ cho dễ so sánh theo cột.
            $sheet->getStyle("H{$excelRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'font' => ['bold' => true],
            ]);

            // Tự giãn chiều cao dòng theo số mức trong "Các mức" (mỗi mức 1 dòng ~16px).
            $levelCount = substr_count((string) ($row['levels_text'] ?? ''), ';') + 1;
            $sheet->getRowDimension($excelRow)->setRowHeight(max(20, 16 * $levelCount));

            // Merge ô "Mã loại tiêu chí"/"Tên loại tiêu chí" theo nhóm liên tiếp cùng loại.
            if ($typeCode !== $groupTypeCode) {
                if ($groupStartRow !== null && $groupStartRow < $excelRow - 1) {
                    $sheet->mergeCells("B{$groupStartRow}:B".($excelRow - 1));
                    $sheet->mergeCells("C{$groupStartRow}:C".($excelRow - 1));
                }
                $groupStartRow = $excelRow;
                $groupTypeCode = $typeCode;
            }
        }

        $lastDataRow = $headerRow + max(count($sorted), 1);

        // Đóng nhóm merge cuối cùng.
        if ($groupStartRow !== null && $groupStartRow < $lastDataRow) {
            $sheet->mergeCells("B{$groupStartRow}:B{$lastDataRow}");
            $sheet->mergeCells("C{$groupStartRow}:C{$lastDataRow}");
        }
        if (count($sorted) > 0) {
            $sheet->getStyle("B".($headerRow + 1).":C{$lastDataRow}")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Viền trong mảnh cho toàn vùng dữ liệu, viền ngoài dày hơn để tách khối rõ ràng.
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::BORDER],
                ],
            ],
        ]);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => self::BORDER_OUTER],
                ],
            ],
        ]);

        $widths = [6, 14, 22, 26, 12, 30, 40, 12, 14, 16, 16, 8, 20, 20, 16];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow);
        $sheet->getHeaderFooter()
            ->setOddFooter('&LVA Workspace&CTiêu chí đánh giá&RTrang &P / &N');
        $sheet->getSheetView()->setZoomScale(110);
    }
}
