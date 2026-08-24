<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ActivityLogExcelExporter
{
    private const PRIMARY = '9A0036';

    private const PRIMARY_SOFT = 'FFE0E4';

    private const HEADER_TEXT = 'FFFFFF';

    private const ZEBRA = 'F7F7F8';

    private const BORDER = 'E5E5E8';

    private const TEXT = '1A1A1A';

    private const MUTED = '6B6B6F';

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $filterLabels
     */
    public function download(
        array $rows,
        array $filters,
        array $filterLabels,
        string $exportKind,
        ?User $exportedBy,
        int $matchedCount,
        int $limit,
        string $filename,
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)->setColor(
            new \PhpOffice\PhpSpreadsheet\Style\Color(self::TEXT),
        );

        $this->fillDataSheet($spreadsheet->getActiveSheet(), $rows, $matchedCount, $limit);
        $this->fillInfoSheet(
            $spreadsheet->createSheet(),
            $filters,
            $filterLabels,
            $exportKind,
            $exportedBy,
            $matchedCount,
            count($rows),
            $limit,
        );

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'activity_xlsx_');
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
    private function fillDataSheet(Worksheet $sheet, array $rows, int $matchedCount, int $limit): void
    {
        $sheet->setTitle('Nhật ký');
        $headers = [
            'STT',
            'Thời gian',
            'Ngày',
            'Giờ',
            'Người thực hiện',
            'Email',
            'Loại thao tác',
            'Việc đã làm',
            'Đối tượng',
            'Mã đối tượng',
            'Chi tiết thêm',
            'Địa chỉ mạng',
            'Trình duyệt',
            'Mã bản ghi',
        ];
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRow = 4;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'NHẬT KÝ HOẠT ĐỘNG');
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
            'Xuất lúc %s  ·  %s dòng trong file%s',
            now()->format('d/m/Y H:i'),
            number_format(count($rows), 0, ',', '.'),
            $matchedCount > $limit
                ? ' (giới hạn '.$limit.' dòng đầu, còn '.number_format($matchedCount - $limit, 0, ',', '.').' dòng chưa xuất)'
                : '',
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
            $carbon = $created ? \Illuminate\Support\Carbon::parse($created) : null;

            $values = [
                $i + 1,
                $carbon?->format('d/m/Y H:i:s') ?? '',
                $carbon?->format('d/m/Y') ?? '',
                $carbon?->format('H:i:s') ?? '',
                $row['actor_name'] ?: 'Hệ thống',
                $row['actor_email'] ?? '',
                $row['action_label'] ?? ($row['action'] ?? ''),
                $row['description'] ?? '',
                $row['subject_label'] ?? '',
                $row['subject_id'] ?? '',
                $row['properties_summary'] ?? '',
                $row['ip_address'] ?? '',
                $row['browser'] ?? ($row['user_agent'] ?? ''),
                $row['id'] ?? '',
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
            $sheet->getRowDimension($excelRow)->setRowHeight(-1);
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

        $widths = [8, 20, 14, 12, 22, 28, 22, 42, 18, 14, 36, 18, 22, 12];
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
            ->setOddFooter('&LVA Workspace&CNhật ký hoạt động&RTrang &P / &N');
        $sheet->getSheetView()->setZoomScale(110);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $filterLabels
     */
    private function fillInfoSheet(
        Worksheet $sheet,
        array $filters,
        array $filterLabels,
        string $exportKind,
        ?User $exportedBy,
        int $matchedCount,
        int $exportedCount,
        int $limit,
    ): void {
        $sheet->setTitle('Thông tin xuất');

        $kindLabel = match ($exportKind) {
            'date' => 'Theo khoảng ngày',
            'user' => 'Theo người dùng',
            default => 'Theo bộ lọc hiện tại',
        };

        $lines = [
            ['Mục', 'Nội dung'],
            ['Tiêu đề', 'Nhật ký hoạt động'],
            ['Cách xuất', $kindLabel],
            ['Thời điểm xuất', now()->format('d/m/Y H:i:s')],
            ['Người xuất', $exportedBy?->name ?: '—'],
            ['Email người xuất', $exportedBy?->email ?: '—'],
            ['Số dòng khớp bộ lọc', number_format($matchedCount, 0, ',', '.')],
            ['Số dòng trong file', number_format($exportedCount, 0, ',', '.')],
            ['Giới hạn mỗi lần xuất', number_format($limit, 0, ',', '.').' dòng'],
        ];

        $filterRows = [
            ['Tìm kiếm', $filters['q'] !== '' ? $filters['q'] : 'Không lọc'],
            ['Loại thao tác', $filterLabels['action'] ?? ($filters['action'] !== '' ? $filters['action'] : 'Tất cả')],
            ['Người dùng', $filterLabels['actor'] ?? ($filters['actor_id'] !== '' ? $filters['actor_id'] : 'Tất cả')],
            ['Từ ngày', $filters['date_from'] !== '' ? $this->displayDate($filters['date_from']) : 'Không giới hạn'],
            ['Đến ngày', $filters['date_to'] !== '' ? $this->displayDate($filters['date_to']) : 'Không giới hạn'],
            ['Địa chỉ mạng', $filters['ip'] !== '' ? $filters['ip'] : 'Không lọc'],
            ['Đối tượng', $filterLabels['subject_type'] ?? ($filters['subject_type'] !== '' ? $filters['subject_type'] : 'Tất cả')],
        ];

        $row = 1;
        foreach ([...$lines, ['', ''], ['Điều kiện lọc', ''], ...$filterRows] as $pair) {
            $sheet->setCellValue("A{$row}", $pair[0]);
            $sheet->setCellValue("B{$row}", $pair[1]);
            $row++;
        }

        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT], 'name' => 'Calibri'],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::PRIMARY],
            ],
        ]);
        $sheet->getStyle('A1:B'.($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::BORDER],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2:A'.($row - 1))->getFont()->setBold(true)->setColor(
            new \PhpOffice\PhpSpreadsheet\Style\Color(self::MUTED),
        );
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(56);
        $sheet->getRowDimension(1)->setRowHeight(22);
    }

    private function displayDate(string $date): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
