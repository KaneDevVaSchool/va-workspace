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
 * Xuất danh sách Mẫu đánh giá ra file Excel (.xlsx). Cùng khuôn mẫu với
 * EvaluationCriteriaExcelExporter — dòng 1 tiêu đề lớn, dòng 2 ghi chú,
 * dòng 4 header cột, từ dòng 5 dữ liệu.
 *
 * CHỈ XUẤT (đọc) — không có chiều Nhập ngược lại cho Mẫu đánh giá (khác
 * tiêu chí đánh giá, phẳng 1 dòng = 1 bản ghi). Mẫu có cấu trúc lồng nhau
 * (N-N tiêu chí kèm trọng số riêng, N-N vị trí, trường tùy biến JSON)
 * không phẳng an toàn thành 1 dòng Excel để nhập lại — quyết định đã chốt
 * 2026-08-26, xem plans/2026-08-26-mau-danh-gia.md §7 PR6.
 *
 * Tiêu chí/vị trí/trường tùy biến của mỗi mẫu được gộp thành text mô tả
 * trong cùng 1 dòng (không tách bảng con) — đủ để xem/lưu trữ, không nhằm
 * mục đích tái tạo lại dữ liệu.
 */
class EvaluationTemplateExcelExporter
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
        'Mã mẫu',
        'Tên mẫu đánh giá',
        'Mô tả',
        'Phòng ban',
        'Dùng chung toàn hệ thống',
        'Trạng thái',
        'Số tiêu chí',
        'Tiêu chí đánh giá (tên: trọng số; …)',
        'Vị trí đánh giá',
        'Trường tùy biến (nhãn: loại; …)',
        'Điểm tối đa',
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

        $path = tempnam(sys_get_temp_dir(), 'eval_templates_xlsx_');
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
        $sheet->setTitle('Mẫu đánh giá');
        $headers = self::HEADERS;
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRow = self::HEADER_ROW;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'MẪU ĐÁNH GIÁ');
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
            'Xuất lúc %s bởi %s · %s dòng · Chỉ để xem/lưu trữ, không dùng để nhập lại',
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

        foreach ($rows as $i => $row) {
            $excelRow = $headerRow + 1 + $i;
            $created = $row['created_at'] ?? null;
            $carbon = $created ? Carbon::parse($created) : null;

            $values = [
                $i + 1,
                $row['code'] ?? '',
                $row['name'] ?? '',
                $row['description'] ?? '',
                $row['department_name'] ?? '',
                $row['is_global_label'] ?? '',
                $row['status_label'] ?? '',
                $row['criteria_count'] ?? 0,
                $row['criteria_text'] ?? '',
                $row['positions_text'] ?? '',
                $row['custom_fields_text'] ?? '',
                $row['max_score'] ?? '',
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

            // Nền nhạt theo trạng thái, đè lên zebra ở đúng ô "Trạng thái" (cột G).
            $sheet->getStyle("G{$excelRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => ($row['status_label'] ?? '') === 'Hoạt động' ? self::STATUS_ACTIVE_BG : self::STATUS_INACTIVE_BG],
                ],
            ]);

            // Điểm tối đa (cột L): căn phải, in đậm nhẹ cho dễ so sánh theo cột.
            $sheet->getStyle("L{$excelRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'font' => ['bold' => true],
            ]);

            // Tự giãn chiều cao dòng theo số tiêu chí (mỗi tiêu chí ~1 dòng trong ô wrap).
            $criteriaLineCount = substr_count((string) ($row['criteria_text'] ?? ''), ';') + 1;
            $sheet->getRowDimension($excelRow)->setRowHeight(max(20, 16 * $criteriaLineCount));
        }

        $lastDataRow = $headerRow + max(count($rows), 1);

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

        $widths = [6, 14, 26, 30, 18, 12, 14, 12, 40, 22, 30, 12, 20, 20, 16];
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
            ->setOddFooter('&LVA Workspace&CMẫu đánh giá&RTrang &P / &N');
        $sheet->getSheetView()->setZoomScale(110);
    }
}
