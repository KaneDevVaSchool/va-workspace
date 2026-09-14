//
// Xuất danh sách công việc (tab Công việc — ProjectDetail) ra CSV / Excel
// (.xlsx) / PDF — client-side, dùng đúng danh sách đang hiển thị trên tab
// (đã áp filter/tìm kiếm) và đúng cột người dùng chọn. Cùng khuôn với
// reportExport.js (tab Báo cáo): CSV tự viết, XLSX qua thư viện xlsx, PDF
// qua jsPDF + jspdf-autotable + font Be Vietnam Pro nhúng riêng.
//

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

function fileStamp(project) {
  const stamp = new Date().toISOString().slice(0, 10);
  const code = String(project?.code || project?.id || 'du-an').replace(/[^\w.-]+/g, '_');
  return `${code}_${stamp}`;
}

/**
 * @param {{ project: object|null, columns: {key: string, label: string}[], rows: string[][] }} ctx
 *   columns: cột theo đúng thứ tự đang xuất. rows: mỗi dòng là mảng chuỗi đã format sẵn, khớp thứ tự columns.
 */
export function exportTaskListCsv(ctx) {
  const { columns, rows } = ctx;
  const header = columns.map((col) => col.label);
  const lines = [header.map(csvCell).join(','), ...rows.map((row) => row.map(csvCell).join(','))];
  const blob = new Blob([`﻿${lines.join('\n')}`], { type: 'text/csv;charset=utf-8' });
  downloadBlob(blob, `cong-viec_${fileStamp(ctx.project)}.csv`);
}

/** Độ rộng cột Excel theo nội dung thực tế (nhãn + vài dòng dữ liệu đầu) — tránh cột quá hẹp làm chữ bị cắt/xuống dòng xấu. */
function columnWidthCh(col, rows, colIndex) {
  const sample = rows.slice(0, 50).map((row) => String(row[colIndex] ?? '').length);
  const maxLen = Math.max(col.label.length, ...sample, 0);
  const base = col.key === 'title' ? 28 : 10;
  return { wch: Math.min(Math.max(maxLen + 2, base), 40) };
}

export async function exportTaskListXlsx(ctx) {
  const XLSX = await import('xlsx');
  const { columns, rows } = ctx;
  const header = columns.map((col) => col.label);
  const aoa = [header, ...rows];
  const sheet = XLSX.utils.aoa_to_sheet(aoa);

  sheet['!cols'] = columns.map((col, index) => columnWidthCh(col, rows, index));
  sheet['!freeze'] = { xSplit: 0, ySplit: 1 };
  sheet['!autofilter'] = { ref: `A1:${XLSX.utils.encode_col(columns.length - 1)}1` };

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, sheet, 'Công việc');
  const arrayBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
  downloadBlob(
    new Blob([arrayBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
    `cong-viec_${fileStamp(ctx.project)}.xlsx`,
  );
}

export async function exportTaskListPdf(ctx) {
  const [{ default: JsPDF }, autoTableModule, { loadPdfFont }] = await Promise.all([
    import('jspdf'),
    import('jspdf-autotable'),
    import('./pdfFont.js'),
  ]);
  const autoTable = autoTableModule.default;
  const font = await loadPdfFont();

  const { project, columns, rows } = ctx;

  const doc = new JsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
  doc.addFileToVFS('BeVietnamPro-Regular.ttf', font.regular);
  doc.addFileToVFS('BeVietnamPro-Bold.ttf', font.bold);
  doc.addFont('BeVietnamPro-Regular.ttf', 'BeVietnamPro', 'normal');
  doc.addFont('BeVietnamPro-Bold.ttf', 'BeVietnamPro', 'bold');
  doc.setFont('BeVietnamPro', 'normal');

  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 32;

  doc.setFont('BeVietnamPro', 'bold');
  doc.setFontSize(15);
  doc.setTextColor(30, 30, 32);
  doc.text(`Danh sách công việc — ${project?.name || ''}`, margin, 34);

  doc.setFont('BeVietnamPro', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(110, 110, 118);
  const meta = [
    project?.code ? `Mã dự án: ${project.code}` : null,
    `Xuất lúc: ${new Date().toLocaleString('vi-VN')}`,
    `${rows.length} dòng`,
  ]
    .filter(Boolean)
    .join('   •   ');
  doc.text(meta, margin, 50);

  // Nhiều cột thì chữ nhỏ lại một chút để nhãn dài (vd. "Mức độ quan trọng")
  // không bị bẻ từng ký tự một dòng.
  const fontSize = columns.length > 10 ? 7.5 : columns.length > 6 ? 8 : 8.5;

  autoTable(doc, {
    startY: 64,
    margin: { left: margin, right: margin },
    styles: {
      font: 'BeVietnamPro',
      fontSize,
      cellPadding: 4,
      textColor: [40, 40, 44],
      lineColor: [220, 220, 224],
      lineWidth: 0.5,
      overflow: 'linebreak',
      minCellWidth: 44,
    },
    headStyles: {
      font: 'BeVietnamPro',
      fontStyle: 'bold',
      fillColor: [255, 255, 255],
      textColor: [30, 30, 32],
      halign: 'center',
      lineWidth: 0.75,
    },
    columnStyles: columns.reduce((acc, col, index) => {
      if (col.key === 'title') acc[index] = { halign: 'left', cellWidth: 150, minCellWidth: 100 };
      return acc;
    }, {}),
    head: [columns.map((col) => col.label)],
    body: rows,
    theme: 'grid',
  });

  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i += 1) {
    doc.setPage(i);
    doc.setFont('BeVietnamPro', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(150, 150, 156);
    doc.text(`Trang ${i}/${pageCount}`, pageWidth - margin, doc.internal.pageSize.getHeight() - 16, { align: 'right' });
  }

  doc.save(`cong-viec_${fileStamp(project)}.pdf`);
}
