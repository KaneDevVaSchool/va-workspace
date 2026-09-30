<?php

namespace Modules\FeatureRequest\App\Services;

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
 * Xuất Excel danh sách ghi nhận yêu cầu tính năng — cùng bảng màu và bố cục
 * với ActivityLogExcelExporter (sheet dữ liệu + sheet thông tin xuất) để mọi
 * file Excel của hệ thống trông như nhau.
 */
class FeatureRequestExcelExporter
{
    private const PRIMARY = '9A0036';

    private const PRIMARY_SOFT = 'FFE0E4';

    private const HEADER_TEXT = 'FFFFFF';

    private const ZEBRA = 'F7F7F8';

    private const BORDER = 'E5E5E8';

    private const TEXT = '1A1A1A';

    private const MUTED = '6B6B6F';

    /** @var array<string, string> */
    private const STATUS_LABEL = [
        'pending' => 'Chờ ghi nhận',
        'reviewing' => 'Đang xem xét',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'done' => 'Đã hoàn thành',
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $filterLabels
     * @param  array<string, int>  $counts
     */
    public function download(
        array $rows,
        array $filters,
        array $filterLabels,
        array $counts,
        string $exportKind,
        ?User $exportedBy,
        int $matchedCount,
        int $limit,
        string $filename,
    ): BinaryFileResponse {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)->setColor(
            new Color(self::TEXT),
        );

        $this->fillDataSheet($spreadsheet->getActiveSheet(), $rows, $matchedCount, $limit);
        $this->fillDepartmentSheet($spreadsheet->createSheet(), $rows);
        $this->fillInfoSheet(
            $spreadsheet->createSheet(),
            $filters,
            $filterLabels,
            $counts,
            $exportKind,
            $exportedBy,
            $matchedCount,
            count($rows),
            $limit,
        );

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'feature_request_xlsx_');
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
        $sheet->setTitle('Ghi nhận');
        $headers = [
            'STT',
            'Thời gian gửi',
            'Ngày gửi',
            'Người gửi',
            'Email người gửi',
            'Phòng ban',
            'Nội dung yêu cầu',
            'Trang đính kèm',
            'Đường dẫn trang',
            'Trạng thái',
            'Người xử lý',
            'Thời điểm xử lý',
            'Ngày hoàn thành dự kiến',
            'Ghi chú tiến độ',
            'Lý do từ chối',
            'Hoàn thành lúc',
            'Số ngày chờ xử lý',
            'Mã bản ghi',
        ];
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRow = 4;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'GHI NHẬN YÊU CẦU TÍNH NĂNG');
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
                ? ' (giới hạn '.number_format($limit, 0, ',', '.').' dòng đầu, còn '
                    .number_format($matchedCount - $limit, 0, ',', '.').' dòng chưa xuất)'
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
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        foreach ($rows as $i => $row) {
            $excelRow = $headerRow + 1 + $i;
            $created = $this->parse($row['created_at'] ?? null);
            $reviewed = $this->parse($row['reviewed_at'] ?? null);
            $done = $this->parse($row['done_at'] ?? null);
            $expected = $this->parse($row['expected_done_at'] ?? null);

            $values = [
                $i + 1,
                $created?->format('d/m/Y H:i') ?? '',
                $created?->format('d/m/Y') ?? '',
                $row['created_by_name'] ?: '—',
                $row['created_by_email'] ?? '',
                $row['department_name'] ?: 'Chưa xác định',
                $row['description'] ?? '',
                $row['page_title'] ?? '',
                $row['page_url'] ?? '',
                self::STATUS_LABEL[$row['status'] ?? ''] ?? ($row['status'] ?? ''),
                $row['reviewed_by_name'] ?? '',
                $reviewed?->format('d/m/Y H:i') ?? '',
                $expected?->format('d/m/Y') ?? '',
                $row['progress_note'] ?? '',
                $row['reject_reason'] ?? '',
                $done?->format('d/m/Y H:i') ?? '',
                $this->waitingDays($created, $reviewed, $done),
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

        $widths = [6, 18, 12, 22, 28, 22, 52, 24, 34, 16, 22, 18, 20, 36, 36, 18, 14, 12];
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        $sheet->getStyle("A{$headerRow}:A{$lastDataRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("Q{$headerRow}:Q{$lastDataRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('A'.($headerRow + 1));
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastDataRow}");
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow);
        $sheet->getHeaderFooter()
            ->setOddFooter('&LVA Workspace&CGhi nhận yêu cầu tính năng&RTrang &P / &N');
        $sheet->getSheetView()->setZoomScale(100);
    }

    /**
     * Sheet tổng hợp theo phòng ban — mỗi phòng một dòng, đếm theo trạng thái,
     * để người đọc thấy ngay phòng nào gửi nhiều và còn tồn đọng bao nhiêu.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function fillDepartmentSheet(Worksheet $sheet, array $rows): void
    {
        $sheet->setTitle('Theo phòng ban');
        $headers = [
            'Phòng ban',
            'Tổng ghi nhận',
            'Chờ ghi nhận',
            'Đang xem xét',
            'Đã duyệt',
            'Đã hoàn thành',
            'Từ chối',
            'Chưa xử lý xong',
        ];
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        $groups = [];
        foreach ($rows as $row) {
            $name = $row['department_name'] ?: 'Chưa xác định';
            if (! isset($groups[$name])) {
                $groups[$name] = [
                    'total' => 0,
                    'pending' => 0,
                    'reviewing' => 0,
                    'approved' => 0,
                    'done' => 0,
                    'rejected' => 0,
                ];
            }
            $groups[$name]['total']++;
            $status = (string) ($row['status'] ?? '');
            if (isset($groups[$name][$status])) {
                $groups[$name][$status]++;
            }
        }
        uasort($groups, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'TỔNG HỢP THEO PHÒNG BAN');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::HEADER_TEXT], 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $headerRow = 3;
        foreach ($headers as $index => $label) {
            $sheet->setCellValue([$index + 1, $headerRow], $label);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT], 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        $row = $headerRow + 1;
        $index = 0;
        $totals = ['total' => 0, 'pending' => 0, 'reviewing' => 0, 'approved' => 0, 'done' => 0, 'rejected' => 0];
        foreach ($groups as $name => $counts) {
            $open = $counts['pending'] + $counts['reviewing'] + $counts['approved'];
            $values = [
                $name,
                $counts['total'],
                $counts['pending'],
                $counts['reviewing'],
                $counts['approved'],
                $counts['done'],
                $counts['rejected'],
                $open,
            ];
            foreach ($values as $col => $value) {
                $sheet->setCellValue([$col + 1, $row], $value);
            }
            if ($index % 2 === 1) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ZEBRA]],
                ]);
            }
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $counts[$key];
            }
            $row++;
            $index++;
        }

        if ($groups !== []) {
            $open = $totals['pending'] + $totals['reviewing'] + $totals['approved'];
            $values = [
                'Tổng cộng',
                $totals['total'],
                $totals['pending'],
                $totals['reviewing'],
                $totals['approved'],
                $totals['done'],
                $totals['rejected'],
                $open,
            ];
            foreach ($values as $col => $value) {
                $sheet->setCellValue([$col + 1, $row], $value);
            }
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'name' => 'Calibri'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY_SOFT]],
            ]);
        } else {
            $sheet->setCellValue("A{$row}", 'Không có ghi nhận nào khớp điều kiện xuất.');
        }

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::BORDER],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("B{$headerRow}:{$lastCol}{$row}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getColumnDimension('A')->setWidth(34);
        foreach (range(2, count($headers)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(16);
        }
        $sheet->freezePane('A'.($headerRow + 1));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $filterLabels
     * @param  array<string, int>  $counts
     */
    private function fillInfoSheet(
        Worksheet $sheet,
        array $filters,
        array $filterLabels,
        array $counts,
        string $exportKind,
        ?User $exportedBy,
        int $matchedCount,
        int $exportedCount,
        int $limit,
    ): void {
        $sheet->setTitle('Thông tin xuất');

        $kindLabel = match ($exportKind) {
            'date' => 'Theo khoảng ngày',
            'department' => 'Theo phòng ban',
            'status' => 'Theo trạng thái',
            default => 'Theo bộ lọc hiện tại',
        };

        $lines = [
            ['Mục', 'Nội dung'],
            ['Tiêu đề', 'Ghi nhận yêu cầu tính năng'],
            ['Cách xuất', $kindLabel],
            ['Thời điểm xuất', now()->format('d/m/Y H:i:s')],
            ['Người xuất', $exportedBy?->name ?: '—'],
            ['Email người xuất', $exportedBy?->email ?: '—'],
            ['Số dòng khớp điều kiện', number_format($matchedCount, 0, ',', '.')],
            ['Số dòng trong file', number_format($exportedCount, 0, ',', '.')],
            ['Giới hạn mỗi lần xuất', number_format($limit, 0, ',', '.').' dòng'],
        ];

        $filterRows = [
            ['Tìm kiếm', ($filters['q'] ?? '') !== '' ? $filters['q'] : 'Không lọc'],
            ['Trạng thái', $filterLabels['status'] ?? 'Tất cả'],
            ['Phòng ban', $filterLabels['department'] ?? 'Tất cả'],
            ['Từ ngày', ($filters['date_from'] ?? '') !== '' ? $this->displayDate($filters['date_from']) : 'Không giới hạn'],
            ['Đến ngày', ($filters['date_to'] ?? '') !== '' ? $this->displayDate($filters['date_to']) : 'Không giới hạn'],
        ];

        $countRows = [
            ['Chờ ghi nhận', number_format($counts['pending'] ?? 0, 0, ',', '.')],
            ['Đang xem xét', number_format($counts['reviewing'] ?? 0, 0, ',', '.')],
            ['Đã duyệt', number_format($counts['approved'] ?? 0, 0, ',', '.')],
            ['Đã hoàn thành', number_format($counts['done'] ?? 0, 0, ',', '.')],
            ['Từ chối', number_format($counts['rejected'] ?? 0, 0, ',', '.')],
            ['Tổng toàn hệ thống', number_format($counts['total'] ?? 0, 0, ',', '.')],
        ];

        $row = 1;
        $sectionRows = [];
        $allRows = [
            ...$lines,
            ['', ''],
            ['Điều kiện lọc', ''],
            ...$filterRows,
            ['', ''],
            ['Thống kê toàn hệ thống', ''],
            ...$countRows,
        ];
        foreach ($allRows as $pair) {
            if ($pair[1] === '' && $pair[0] !== '' && $row > 1) {
                $sectionRows[] = $row;
            }
            $sheet->setCellValue("A{$row}", $pair[0]);
            $sheet->setCellValue("B{$row}", $pair[1]);
            $row++;
        }

        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT], 'name' => 'Calibri'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY]],
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
        $sheet->getStyle('A2:A'.($row - 1))->getFont()->setBold(true)->setColor(new Color(self::MUTED));

        foreach ($sectionRows as $sectionRow) {
            $sheet->getStyle("A{$sectionRow}:B{$sectionRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => self::PRIMARY], 'name' => 'Calibri'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PRIMARY_SOFT]],
            ]);
        }

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(56);
        $sheet->getRowDimension(1)->setRowHeight(22);
    }

    /** Số ngày từ lúc gửi tới lúc được xử lý (hoặc tới hôm nay nếu còn treo). */
    private function waitingDays(?Carbon $created, ?Carbon $reviewed, ?Carbon $done): string
    {
        if ($created === null) {
            return '';
        }

        $end = $done ?? $reviewed ?? now();

        return (string) $created->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());
    }

    private function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function displayDate(string $date): string
    {
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
