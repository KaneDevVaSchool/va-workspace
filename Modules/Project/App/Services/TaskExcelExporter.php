<?php

namespace Modules\Project\App\Services;

use App\Models\User;
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
 * Xuất danh sách công việc ra file Excel (.xlsx). Cùng khuôn mẫu với
 * ProjectExcelExporter — dòng 1 tiêu đề lớn, dòng 2 ghi chú, dòng 4 header
 * cột, từ dòng 5 dữ liệu.
 *
 * Cột 'code'/'project_code' luôn xuất — là điểm đối chiếu update khi nhập
 * lại (mã công việc xác định dòng cần sửa, mã dự án xác định project đích
 * khi tạo mới). Thứ tự + tên key trong COLUMNS phải khớp TaskExcelImporter
 * (cột theo vị trí cố định COL_*) — KHÔNG đổi thứ tự khoá.
 */
class TaskExcelExporter
{
    private const PRIMARY = '9A0036';

    private const PRIMARY_SOFT = 'FFE0E4';

    private const HEADER_TEXT = 'FFFFFF';

    private const ZEBRA = 'F7F7F8';

    private const BORDER = 'E5E5E8';

    private const BORDER_OUTER = 'C9C9CE';

    private const TEXT = '1A1A1A';

    public const HEADER_ROW = 4;

    /** key => [label, width]. Thứ tự khớp 1-1 vị trí cột cố định trong TaskExcelImporter. */
    public const COLUMNS = [
        'code' => ['Mã công việc', 16],
        'project_code' => ['Mã dự án', 14],
        'title' => ['Tên công việc', 32],
        'type_label' => ['Loại', 14],
        'status_label' => ['Trạng thái', 16],
        'priority_label' => ['Mức độ ưu tiên', 16],
        'assignee_email' => ['Người thực hiện (email)', 24],
        'manager_email' => ['Người quản lý (email)', 24],
        'start_date' => ['Ngày bắt đầu', 14],
        'end_date' => ['Ngày kết thúc', 14],
        'progress_type_label' => ['Cách tính tiến độ', 18],
        'progress_percent' => ['Tiến độ (%)', 12],
        'progress_number' => ['Khối lượng hoàn thành', 18],
        'progress_total' => ['Khối lượng cần hoàn thành', 20],
        'unit' => ['Đơn vị', 14],
        'estimated_hours' => ['Thời gian dự kiến (giờ)', 18],
        'weight' => ['Tỷ trọng (%)', 14],
        'description' => ['Mô tả', 32],
    ];

    /** Cột luôn xuất bất kể $columnKeys chọn gì — cần cho đối chiếu update/tạo mới. */
    private const ALWAYS_KEYS = ['code', 'project_code'];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>|null  $columnKeys  null = xuất đủ mọi cột (COLUMNS) theo đúng thứ tự
     */
    public function download(
        array $rows,
        ?User $exportedBy,
        string $filename,
        ?array $columnKeys = null,
    ): BinaryFileResponse {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)
            ->setColor(new Color(self::TEXT));

        $this->fillDataSheet($spreadsheet->getActiveSheet(), $rows, $exportedBy, $this->resolveColumnKeys($columnKeys));

        $path = tempnam(sys_get_temp_dir(), 'task_xlsx_');
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

    /**
     * @param  list<string>|null  $columnKeys
     * @return list<string>
     */
    private function resolveColumnKeys(?array $columnKeys): array
    {
        $allKeys = array_keys(self::COLUMNS);
        if ($columnKeys === null) {
            return $allKeys;
        }

        $wanted = array_unique(array_merge(self::ALWAYS_KEYS, $columnKeys));

        return array_values(array_filter($allKeys, fn ($key) => in_array($key, $wanted, true)));
    }

    /** @param  list<array<string, mixed>>  $rows  @param  list<string>  $columnKeys */
    private function fillDataSheet(Worksheet $sheet, array $rows, ?User $exportedBy, array $columnKeys): void
    {
        $sheet->setTitle('Công việc');
        $headers = array_merge(['STT'], array_map(fn ($key) => self::COLUMNS[$key][0], $columnKeys));
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRow = self::HEADER_ROW;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'DANH SÁCH CÔNG VIỆC');
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
            'Xuất lúc %s bởi %s · %s dòng · Nhập lại: bắt buộc Mã dự án + Tên công việc (trừ dòng cập nhật theo Mã công việc).',
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
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        foreach ($rows as $i => $row) {
            $excelRow = $headerRow + 1 + $i;
            $values = array_merge(
                [$i + 1],
                array_map(fn ($key) => $row[$key] ?? '', $columnKeys),
            );

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

            $lineCount = substr_count((string) ($row['description'] ?? ''), "\n") + 1;
            $sheet->getRowDimension($excelRow)->setRowHeight(max(20, 16 * min($lineCount, 4)));
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

        $widths = array_merge([6], array_map(fn ($key) => self::COLUMNS[$key][1], $columnKeys));
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
            ->setOddFooter('&LVA Workspace&CDanh sách công việc&RTrang &P / &N');
        $sheet->getSheetView()->setZoomScale(110);
    }
}
