<?php

namespace Modules\Credential\App\Services;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Xuất báo cáo chi phí tài khoản dịch vụ ra file Excel (.xlsx) — 3 sheet
 * để dễ ra quyết định: "Tổng quan" (số liệu lớn + breakdown theo nhà
 * cung cấp/loại tài khoản), "Chi tiết" (từng tài khoản), "Dự phóng 12
 * tháng" (nếu có dữ liệu forecast truyền vào). Cùng khuôn mẫu màu/viền
 * với ProjectExcelExporter.
 */
class CredentialExcelExporter
{
    private const PRIMARY = '9A0036';

    private const PRIMARY_SOFT = 'FFE0E4';

    private const SECONDARY = '009082';

    private const SECONDARY_SOFT = 'E6F5F3';

    private const HEADER_TEXT = 'FFFFFF';

    private const ZEBRA = 'F7F7F8';

    private const BORDER = 'E5E5E8';

    private const BORDER_OUTER = 'C9C9CE';

    private const TEXT = '1A1A1A';

    private const TEXT_MUTED = '6B6B6F';

    public const DETAIL_COLUMNS = [
        'name' => ['Tên', 28],
        'provider' => ['Nhà cung cấp', 20],
        'account_type' => ['Loại tài khoản', 18],
        'access_url' => ['Link truy cập', 32],
        'monthly_cost_vnd' => ['Chi phí/tháng (VNĐ)', 20],
        'purchased_at' => ['Ngày mua', 14],
        'expires_at' => ['Ngày hết hạn', 14],
        'status' => ['Trạng thái', 18],
    ];

    /**
     * @param  array<string, mixed>  $summary  kết quả CredentialService::costSummary()
     * @param  array<string, mixed>|null  $forecast  kết quả CredentialService::costForecast(), null nếu bỏ qua sheet dự phóng
     */
    public function download(array $summary, ?array $forecast, ?User $exportedBy, string $filename): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)
            ->setColor(new Color(self::TEXT));

        $this->fillOverviewSheet($spreadsheet->getActiveSheet(), $summary, $exportedBy);
        $this->fillDetailSheet($spreadsheet->createSheet(), $summary['items']->all());
        if ($forecast !== null) {
            $this->fillForecastSheet($spreadsheet->createSheet(), $forecast);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'credential_xlsx_');
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

    /** Sheet 1 — số liệu lớn dễ đọc + breakdown theo nhà cung cấp/loại tài khoản, đủ để ra quyết định ngay không cần lật sang sheet Chi tiết. */
    private function fillOverviewSheet(Worksheet $sheet, array $summary, ?User $exportedBy): void
    {
        $sheet->setTitle('Tổng quan');

        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'BÁO CÁO CHI PHÍ TÀI KHOẢN DỊCH VỤ');
        $this->styleTitle($sheet, 'A1:D1');
        $sheet->getRowDimension(1)->setRowHeight(32);

        $note = sprintf(
            'Xuất lúc %s bởi %s',
            now()->format('d/m/Y H:i'),
            $exportedBy?->name ?: 'Hệ thống',
        );
        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', $note);
        $this->styleNote($sheet, 'A2:D2');
        $sheet->getRowDimension(2)->setRowHeight(22);

        // 3 ô số liệu lớn — Tổng/tháng, Ước tính/năm, Số tài khoản.
        $stats = [
            ['Tổng chi phí mỗi tháng', $summary['total_monthly'].' đ'],
            ['Ước tính mỗi năm', $summary['total_yearly_estimate'].' đ'],
            ['Số tài khoản đang tính phí', (string) $summary['account_count']],
        ];
        $row = 4;
        foreach ($stats as $i => [$label, $value]) {
            $col = Coordinate::stringFromColumnIndex($i * 2 + 1);
            $colEnd = Coordinate::stringFromColumnIndex($i * 2 + 2);
            $sheet->mergeCells("{$col}{$row}:{$colEnd}{$row}");
            $sheet->setCellValue("{$col}{$row}", $label);
            $sheet->getStyle("{$col}{$row}")->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => self::TEXT_MUTED]],
            ]);
            $sheet->mergeCells("{$col}".($row + 1).":{$colEnd}".($row + 1));
            $sheet->setCellValue("{$col}".($row + 1), $value);
            $sheet->getStyle("{$col}".($row + 1))->applyFromArray([
                'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => self::PRIMARY]],
            ]);
        }
        $sheet->getRowDimension($row)->setRowHeight(16);
        $sheet->getRowDimension($row + 1)->setRowHeight(24);

        // Breakdown theo nhà cung cấp.
        $cursor = $row + 4;
        $cursor = $this->writeBreakdownTable($sheet, $cursor, 'Chi phí theo nhà cung cấp', $summary['by_provider']);

        // Breakdown theo loại tài khoản.
        $cursor += 2;
        $this->writeBreakdownTable($sheet, $cursor, 'Chi phí theo loại tài khoản', $summary['by_account_type']);

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setWidth(22);
        }
    }

    /**
     * Bảng 2 cột (Tên nhóm / Chi phí) — dùng chung cho breakdown theo
     * provider và theo account_type. Trả về số dòng cuối đã dùng.
     *
     * @param  list<array{label: string, amount: float}>  $entries
     */
    private function writeBreakdownTable(Worksheet $sheet, int $startRow, string $title, array $entries): int
    {
        $sheet->mergeCells("A{$startRow}:B{$startRow}");
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->getStyle("A{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::TEXT]],
        ]);

        $headerRow = $startRow + 1;
        $sheet->setCellValue("A{$headerRow}", 'Nhóm');
        $sheet->setCellValue("B{$headerRow}", 'Chi phí/tháng (VNĐ)');
        $sheet->getStyle("A{$headerRow}:B{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECONDARY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $lastRow = $headerRow;
        foreach ($entries as $i => $entry) {
            $lastRow = $headerRow + 1 + $i;
            $sheet->setCellValue("A{$lastRow}", $entry['label']);
            $sheet->setCellValue("B{$lastRow}", $entry['amount']);
            $sheet->getStyle("B{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
            if ($i % 2 === 1) {
                $sheet->getStyle("A{$lastRow}:B{$lastRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ZEBRA]],
                ]);
            }
        }

        $sheet->getStyle("A{$headerRow}:B{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
        ]);

        return $lastRow;
    }

    /** Sheet 2 — từng tài khoản, đủ chi tiết để đối chiếu/gia hạn. */
    private function fillDetailSheet(Worksheet $sheet, array $rows): void
    {
        $sheet->setTitle('Chi tiết');
        $columnKeys = array_keys(self::DETAIL_COLUMNS);
        $headers = array_merge(['STT'], array_map(fn ($key) => self::DETAIL_COLUMNS[$key][0], $columnKeys));
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRow = 2;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'CHI TIẾT TỪNG TÀI KHOẢN');
        $this->styleTitle($sheet, "A1:{$lastCol}1");
        $sheet->getRowDimension(1)->setRowHeight(28);

        foreach ($headers as $index => $label) {
            $sheet->setCellValue([$index + 1, $headerRow], $label);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        $costColIndex = array_search('monthly_cost_vnd', $columnKeys, true);
        $costCol = $costColIndex !== false ? Coordinate::stringFromColumnIndex($costColIndex + 2) : null;

        foreach ($rows as $i => $row) {
            $excelRow = $headerRow + 1 + $i;
            $values = array_merge([$i + 1], array_map(fn ($key) => $row[$key] ?? '', $columnKeys));
            foreach ($values as $col => $value) {
                $sheet->setCellValue([$col + 1, $excelRow], $value);
            }

            $rowStyle = ['alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
            if ($i % 2 === 1) {
                $rowStyle['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ZEBRA]];
            }
            $sheet->getStyle("A{$excelRow}:{$lastCol}{$excelRow}")->applyFromArray($rowStyle);

            if ($costCol !== null) {
                $sheet->getStyle("{$costCol}{$excelRow}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        // Dòng tổng cộng cuối bảng.
        $totalRow = $headerRow + count($rows) + 1;
        if ($costCol !== null && count($rows) > 0) {
            $sheet->mergeCells("A{$totalRow}:".chr(ord($costCol) - 1)."{$totalRow}");
            $sheet->setCellValue("A{$totalRow}", 'TỔNG CỘNG');
            $sheet->setCellValue("{$costCol}{$totalRow}", "=SUM({$costCol}".($headerRow + 1).":{$costCol}".($headerRow + count($rows)).')');
            $sheet->getStyle("{$costCol}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY_SOFT]],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::PRIMARY]]],
            ]);
        }

        $lastDataRow = max($totalRow, $headerRow + 1);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
        ]);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BORDER_OUTER]]],
        ]);

        $widths = array_merge([6], array_map(fn ($key) => self::DETAIL_COLUMNS[$key][1], $columnKeys));
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        $sheet->freezePane('A3');
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}".($headerRow + max(count($rows), 1)));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getHeaderFooter()->setOddFooter('&LVA Workspace&CChi tiết chi phí tài khoản dịch vụ&RTrang &P / &N');
    }

    /** Sheet 3 (tuỳ chọn) — dự phóng 12 tháng tới, kèm tháng nào có tài khoản cần gia hạn. */
    private function fillForecastSheet(Worksheet $sheet, array $forecast): void
    {
        $sheet->setTitle('Dự phóng 12 tháng');

        $sheet->mergeCells('A1:C1');
        $sheet->setCellValue('A1', 'DỰ PHÓNG CHI PHÍ 12 THÁNG TỚI');
        $this->styleTitle($sheet, 'A1:C1');
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells('A2:C2');
        $sheet->setCellValue('A2', $forecast['note'] ?? '');
        $this->styleNote($sheet, 'A2:C2');

        $headerRow = 4;
        $sheet->setCellValue("A{$headerRow}", 'Tháng');
        $sheet->setCellValue("B{$headerRow}", 'Tổng chi phí (VNĐ)');
        $sheet->setCellValue("C{$headerRow}", 'Tài khoản hết hạn trong tháng');
        $sheet->getStyle("A{$headerRow}:C{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $lastRow = $headerRow;
        foreach ($forecast['months'] as $i => $month) {
            $lastRow = $headerRow + 1 + $i;
            $expiringNames = collect($month['expiring'])->pluck('name')->implode(', ');
            $sheet->setCellValue("A{$lastRow}", $month['month']);
            $sheet->setCellValue("B{$lastRow}", $month['total_vnd']);
            $sheet->getStyle("B{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->setCellValue("C{$lastRow}", $expiringNames !== '' ? $expiringNames : '—');
            if (! empty($month['expiring'])) {
                $sheet->getStyle("A{$lastRow}:C{$lastRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECONDARY_SOFT]],
                ]);
            } elseif ($i % 2 === 1) {
                $sheet->getStyle("A{$lastRow}:C{$lastRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ZEBRA]],
                ]);
            }
        }

        $sheet->getStyle("A{$headerRow}:C{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
        ]);

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(48);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    }

    private function styleTitle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::HEADER_TEXT], 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function styleNote(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => self::PRIMARY], 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY_SOFT]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }
}
