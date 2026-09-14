//
// Xuất báo cáo dự án (tab Báo cáo) ra CSV / Excel (.xlsx) / PDF — dùng chung
// dữ liệu sheetRows + foot từ ProjectReportTab.vue. 3 định dạng cùng 1 bộ
// cột: Nhân sự, Email, rồi 4 nhóm (Tổng công việc, Đang thực hiện, Hoàn
// thành, Kết quả) × 3 cột con, cộng dòng "Dự án" tổng cuối bảng.
//

const GROUPS_META = [
  { label: 'Tổng công việc', cols: ['Số lượng', 'Đúng hạn', 'Quá hạn'] },
  { label: 'Đang thực hiện', cols: ['Số lượng', 'Trong hạn', 'Quá hạn'] },
  { label: 'Hoàn thành', cols: ['Số lượng', 'Đúng hạn', 'Trễ hạn'] },
  { label: 'Kết quả', cols: ['Tiến độ', 'Giờ dự kiến', 'Giờ thực tế'] },
];

function statCells(stats, formatNumber, formatPercent) {
  return [
    stats.total_count,
    stats.total_on_time,
    stats.total_overdue,
    stats.in_progress_count,
    stats.in_progress_on_time,
    stats.in_progress_overdue,
    stats.completed_count,
    stats.completed_on_time,
    stats.completed_late,
    formatPercent(stats.progress),
    formatNumber(stats.est_hours),
    formatNumber(stats.work_hours),
  ];
}

function buildTable({ project, rows, foot, formatNumber, formatPercent }) {
  const groupHeader = ['', '', ...GROUPS_META.flatMap((g) => [g.label, '', ''])];
  const colHeader = ['Nhân sự', 'Email', ...GROUPS_META.flatMap((g) => g.cols)];
  const body = rows.map((row) => [
    row.name,
    row.email || '',
    ...statCells(row.stats, formatNumber, formatPercent),
  ]);
  if (rows.length) {
    body.push(['Dự án (tổng)', '', ...statCells(foot, formatNumber, formatPercent)]);
  }
  return { groupHeader, colHeader, body };
}

function fileStamp(project) {
  const stamp = new Date().toISOString().slice(0, 10);
  const code = String(project?.code || project?.id || 'du-an').replace(/[^\w.-]+/g, '_');
  return `${code}_${stamp}`;
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

function csvCell(value) {
  const text = String(value ?? '');
  if (/[",\n]/.test(text)) return `"${text.replace(/"/g, '""')}"`;
  return text;
}

export function exportReportCsv(ctx) {
  const { groupHeader, colHeader, body } = buildTable(ctx);
  const lines = [
    groupHeader.map(csvCell).join(','),
    colHeader.map(csvCell).join(','),
    ...body.map((row) => row.map(csvCell).join(',')),
  ];
  const blob = new Blob([`﻿${lines.join('\n')}`], { type: 'text/csv;charset=utf-8' });
  downloadBlob(blob, `bao-cao-du-an_${fileStamp(ctx.project)}.csv`);
}

export async function exportReportXlsx(ctx) {
  const XLSX = await import('xlsx');
  const { groupHeader, colHeader, body } = buildTable(ctx);
  const aoa = [groupHeader, colHeader, ...body];
  const sheet = XLSX.utils.aoa_to_sheet(aoa);

  // Gộp ô hàng nhóm cột (2 cột đầu Nhân sự/Email + 4 nhóm x 3 cột).
  sheet['!merges'] = [
    { s: { r: 0, c: 0 }, e: { r: 1, c: 0 } },
    { s: { r: 0, c: 1 }, e: { r: 1, c: 1 } },
    ...GROUPS_META.map((_, i) => ({
      s: { r: 0, c: 2 + i * 3 },
      e: { r: 0, c: 4 + i * 3 },
    })),
  ];
  sheet['!cols'] = [
    { wch: 24 },
    { wch: 26 },
    ...GROUPS_META.flatMap(() => [{ wch: 11 }, { wch: 11 }, { wch: 11 }]),
  ];
  sheet['!freeze'] = { xSplit: 0, ySplit: 2 };
  sheet['!autofilter'] = { ref: `A2:${XLSX.utils.encode_col(colHeader.length - 1)}2` };

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, sheet, 'Báo cáo dự án');
  const arrayBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
  downloadBlob(
    new Blob([arrayBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
    `bao-cao-du-an_${fileStamp(ctx.project)}.xlsx`,
  );
}

export async function exportReportPdf(ctx) {
  const [{ default: JsPDF }, autoTableModule, { loadPdfFont }] = await Promise.all([
    import('jspdf'),
    import('jspdf-autotable'),
    import('./pdfFont.js'),
  ]);
  const autoTable = autoTableModule.default;
  const font = await loadPdfFont();

  const { project } = ctx;
  const { body } = buildTable(ctx);

  const doc = new JsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
  doc.addFileToVFS('BeVietnamPro-Regular.ttf', font.regular);
  doc.addFileToVFS('BeVietnamPro-Bold.ttf', font.bold);
  doc.addFont('BeVietnamPro-Regular.ttf', 'BeVietnamPro', 'normal');
  doc.addFont('BeVietnamPro-Bold.ttf', 'BeVietnamPro', 'bold');
  doc.setFont('BeVietnamPro', 'normal');

  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 32;

  // Header: tên dự án + thời điểm xuất.
  doc.setFont('BeVietnamPro', 'bold');
  doc.setFontSize(15);
  doc.setTextColor(30, 30, 32);
  doc.text(`Báo cáo dự án — ${project?.name || ''}`, margin, 34);

  doc.setFont('BeVietnamPro', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(110, 110, 118);
  const meta = [
    project?.code ? `Mã dự án: ${project.code}` : null,
    `Xuất lúc: ${new Date().toLocaleString('vi-VN')}`,
  ]
    .filter(Boolean)
    .join('   •   ');
  doc.text(meta, margin, 50);

  const primary = [154, 0, 54]; // #9a0036 — var(--color-primary)
  const groupRow = [
    { content: 'Nhân sự', colSpan: 2, rowSpan: 2 },
    ...GROUPS_META.map((g) => ({ content: g.label, colSpan: 3 })),
  ];
  const leafRow = ['Email', ...GROUPS_META.flatMap((g) => g.cols)];

  autoTable(doc, {
    startY: 64,
    margin: { left: margin, right: margin },
    styles: {
      font: 'BeVietnamPro',
      fontSize: 8.5,
      cellPadding: 5,
      textColor: [40, 40, 44],
      lineColor: [225, 225, 230],
      lineWidth: 0.5,
    },
    headStyles: {
      font: 'BeVietnamPro',
      fontStyle: 'bold',
      fillColor: primary,
      textColor: [255, 255, 255],
      halign: 'center',
    },
    columnStyles: {
      0: { halign: 'left', cellWidth: 130 },
      1: { halign: 'left', cellWidth: 150 },
    },
    didParseCell(data) {
      if (data.column.index >= 2) data.cell.styles.halign = 'center';
      if (data.section === 'body' && data.row.index === body.length - 1) {
        data.cell.styles.fontStyle = 'bold';
        data.cell.styles.fillColor = [248, 240, 243];
      }
    },
    head: [groupRow, leafRow],
    body,
    theme: 'grid',
    alternateRowStyles: { fillColor: [250, 250, 251] },
  });

  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i += 1) {
    doc.setPage(i);
    doc.setFont('BeVietnamPro', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(150, 150, 156);
    doc.text(`Trang ${i}/${pageCount}`, pageWidth - margin, doc.internal.pageSize.getHeight() - 16, { align: 'right' });
  }

  doc.save(`bao-cao-du-an_${fileStamp(project)}.pdf`);
}
