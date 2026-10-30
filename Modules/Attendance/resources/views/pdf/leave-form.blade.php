<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>{{ $documentCode }} - Đơn nghỉ phép</title>
<style>
    @page { margin: 10mm 13mm 11mm 15mm; }
    body {
        margin: 0;
        color: #000000;
        font-family: 'DejaVu Serif', serif;
        font-size: 11px;
        line-height: 1.22;
    }
    table { width: 100%; border-collapse: collapse; }
    .head { border: 1px solid #000000; margin: 0 0 5px; }
    .head td { border: 1px solid #000000; vertical-align: middle; }
    .logo { width: 21%; text-align: center; padding: 5px 4px; }
    .logo img { width: 62px; height: auto; }
    .title { width: 50%; text-align: center; padding: 8px 6px; }
    h1 {
        margin: 0;
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 0.06em;
        line-height: 1.15;
    }
    .meta { width: 29%; padding: 5px 8px; }
    .meta td { border: 0; padding: 0 0 1px; font-size: 10.5px; line-height: 1.35; vertical-align: baseline; }
    .meta td.k { white-space: nowrap; padding-right: 6px; width: 1%; }
    .dear { margin: 12px 0 3px; }
    .dear td { border: 0; padding: 0 0 1px; vertical-align: baseline; }
    .dear td.k { width: 1%; white-space: nowrap; padding-right: 10px; }
    .kgu { font-style: italic; text-decoration: underline; }
    .facts { margin: 0 0 2px; }
    .facts td { border: 0; padding: 1px 10px 1px 0; vertical-align: baseline; }
    .facts td.lab { width: 1%; white-space: nowrap; padding-right: 6px; }
    .facts td.gap { width: 16px; padding: 0; }
    .facts td.field { border-bottom: 1px dotted #000000; }
    b { font-weight: bold; }
    .lead { margin: 4px 0 3px; text-align: justify; }
    .specs td { border: 0; width: 33.33%; vertical-align: top; padding: 1px 10px 2px 0; }
    .specs td.field { border-bottom: 1px dotted #000000; }
    .specs .cap { font-size: 10.5px; }
    .types { margin: 2px 0 4px; }
    .types td { border: 0; width: 33.33%; vertical-align: top; padding: 1px 8px 1px 0; }
    .mark { font-family: 'DejaVu Sans', sans-serif; }
    .line td { border: 0; padding: 2px 0 1px; vertical-align: baseline; }
    .line td.lab { width: 1%; white-space: nowrap; padding-right: 6px; }
    .line td.field { border-bottom: 1px dotted #000000; }
    .dots { border-bottom: 1px dotted #000000; }
    .pledge { margin: 4px 0 0; }
    .sign td { border: 0; vertical-align: top; }
    .center { text-align: center; }
    .when { font-style: italic; }
    .hint { font-style: italic; font-size: 10.5px; }
    .sign-space { width: 86%; margin: 2px auto 0; }
    .sign-space td { height: 72px; border: 0; border-bottom: 1px solid #000000; }
    .sign-space.roomy td { height: 118px; }
    .sheet-break { page-break-before: always; }
    .yk { margin-top: 0; border-top: 1px solid #000000; }
    .yk td { border: 0; vertical-align: top; padding: 3px 8px 1px 0; }
    .yk td.sig { width: 42%; text-align: center; padding: 3px 0 1px; }
    .yh { margin: 0 0 2px; font-size: 12px; font-weight: bold; text-decoration: underline; }
    .chk { margin: 0 0 1px; }
    .foot { margin: 4px 0 0; font-size: 10px; }
    .mast { margin: 0 0 7px; }
    .mast td { border: 0; vertical-align: top; text-align: center; padding: 0 6px; }
    .mast-org { width: 40%; }
    .mast-nation { width: 60%; }
    .org-name { margin: 0; font-size: 11.5px; font-weight: bold; }
    .org-line { margin: 1px 0 0; font-size: 11px; font-weight: bold; }
    .nation-name {
        margin: 0;
        font-size: 11px;
        font-weight: bold;
        letter-spacing: 2.6px;
        white-space: nowrap;
    }
    .nation-motto {
        margin: 2px 0 0;
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 1px;
        text-decoration: underline;
    }
</style>
</head>
<body>
    <table class="mast">
        <tr>
            <td class="mast-org">
                <p class="org-name">{{ $orgName }}</p>
                <p class="org-line">{{ $unitName }}</p>
                <p class="org-line">Số: {{ $documentCode }}</p>
            </td>
            <td class="mast-nation">
                <p class="nation-name">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
                <p class="nation-motto">Độc lập - Tự do - Hạnh phúc</p>
            </td>
        </tr>
    </table>
    <table class="head">
        <tr>
            <td class="logo">
                @if (! empty($logoPath))
                    <img src="{{ $logoPath }}" alt="">
                @endif
            </td>
            <td class="title">
                <h1>ĐƠN NGHỈ PHÉP</h1>
            </td>
            <td class="meta">
                <table>
                    <tr><td class="k">Kí hiệu:</td><td><b>{{ $formSymbol }}</b></td></tr>
                    <tr><td class="k">Ngày hiệu lực:</td><td>{{ $effectiveDate }}</td></tr>
                    <tr><td class="k">Lần ban hành:</td><td>{{ $revision }}</td></tr>
                    <tr><td class="k">Mã đơn:</td><td><b>{{ $documentCode }}</b></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="dear">
        <tr>
            <td class="k"><span class="kgu">Kính gửi:</span></td>
            <td>- {{ $recipientTitle }}@if ($recipientName !== ''): {{ $recipientName }}@endif;</td>
        </tr>
        <tr>
            <td></td>
            <td>- <b>Phòng Hành chính Nhân sự;</b></td>
        </tr>
        <tr>
            <td></td>
            <td>- <b>{{ $unitName }}</b></td>
        </tr>
    </table>

    <table class="facts">
        <tr>
            <td class="lab">Tôi tên:</td>
            <td class="field"><b>{{ $employeeName }}</b></td>
            <td class="gap"></td>
            <td class="lab">Mã nhân viên:</td>
            <td class="field"><b>{{ $employeeCode }}</b></td>
        </tr>
        <tr>
            <td class="lab">Chức danh:</td>
            <td class="field"><b>{{ $jobTitle }}</b></td>
            <td class="gap"></td>
            <td class="lab">Đơn vị/Phòng ban:</td>
            <td class="field"><b>{{ $workplace }}</b></td>
        </tr>
        <tr>
            <td class="lab">Ngày vào nhận việc:</td>
            <td class="field"><b><u>{{ $hiredAt }}</u></b></td>
            <td class="gap"></td>
            <td class="lab">Ngày chính thức:</td>
            <td class="field"><b><u>{{ $officialAt }}</u></b></td>
        </tr>
    </table>

    <p class="lead">Tôi làm đơn này kính xin Ban lãnh đạo chấp thuận cho tôi được nghỉ phép tại Hệ thống Trường Việt Mỹ từ ngày <b><u>{{ $dateFromDisplay }}</u></b> đến ngày <b><u>{{ $dateToDisplay }}</u></b>.</p>

    <table class="specs">
        <tr>
            <td class="field">
                <div class="cap">Chi tiết</div>
                <b>{{ $detailLabel }}</b>
            </td>
            <td class="field">
                <div class="cap">Buổi</div>
                <b>{{ $sessionLabel }}</b>
            </td>
            <td class="field">
                <div class="cap">Số ngày công</div>
                <b>{{ $totalDaysLabel }}</b> ngày
            </td>
        </tr>
    </table>

    <table class="line one-field">
        <tr>
            <td class="lab">Loại nghỉ:</td>
            <td class="field"><b>{{ $leaveTypeLabel }}</b></td>
        </tr>
    </table>
    <table class="types">
        <tr>
            @foreach ($buckets as $bucket)
                <td>
                    <p class="chk"><span class="mark">{{ $bucket['checked'] ? '☑' : '☐' }}</span> {{ $bucket['label'] }}</p>
                    <p class="chk">Số ngày: <b class="dots">{{ $bucket['days'] }}</b><span class="dots">........</span></p>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="line">
        <tr>
            <td class="lab">Lý do:</td>
            <td class="rest"><b class="dots">{{ $reason }}</b><span class="dots">……………………………………</span></td>
        </tr>
        <tr>
            <td class="lab">Ghi chú duyệt:</td>
            <td class="rest"><b class="dots">{{ $approvalNote ?: '………………' }}</b><span class="dots">…………………………</span></td>
        </tr>
    </table>

    <p class="pledge">Tôi cam kết quay lại làm việc sau khi kết thúc thời gian nghỉ phép.</p>

    <table class="sign">
        <tr>
            <td style="width: 54%;"></td>
            <td class="center">
                <p class="when">{{ $employeeSignDate }}</p>
                <p><b>Người làm đơn</b></p>
                <p class="hint">(Ký và ghi rõ họ tên)</p>
                <table class="sign-space"><tr><td>&nbsp;</td></tr></table>
                <p><b>{{ $employeeName }}</b></p>
            </td>
        </tr>
    </table>

    <table class="yk">
        <tr>
            <td>
                <p class="yh">Ý kiến của Trưởng đơn vị</p>
                <p>Ngày nhận đơn: <b>{{ $receivedAt }}</b></p>
                <p><b>Đề xuất:</b></p>
                <p class="chk"><span class="mark">☑</span> Đồng ý cho nghỉ phép</p>
                <p class="chk"><span class="mark">☐</span> Ý kiến khác: ………………</p>
            </td>
            <td class="sig">
                <p class="when">{{ $approverSignDate }}</p>
                <p><b>Trưởng đơn vị</b></p>
                <p class="hint">(Ký và ghi rõ họ tên)</p>
                <table class="sign-space"><tr><td>&nbsp;</td></tr></table>
                <p><b>{{ $approverName }}</b></p>
            </td>
        </tr>
    </table>

    <table class="yk sheet-break">
        <tr>
            <td>
                <p class="yh">Ý kiến Phòng HCNS</p>
                <p>Ngày nhận đơn: ………………</p>
                <p><b>Đề xuất:</b></p>
                <p class="chk"><span class="mark">☐</span> Đồng ý</p>
                <p class="chk"><span class="mark">☐</span> Ý kiến khác: ………………</p>
            </td>
            <td class="sig">
                <p class="when">………, ngày …… tháng …… năm 20…</p>
                <p><b>Trưởng phòng/ Giám đốc HCNS</b></p>
                <p class="hint">(Ký và ghi rõ họ tên)</p>
                <table class="sign-space roomy"><tr><td>&nbsp;</td></tr></table>
            </td>
        </tr>
    </table>

    <table class="yk">
        <tr>
            <td>
                <p class="yh">Phê duyệt của TGĐ</p>
                <p><b>Phê duyệt:</b></p>
                <p class="chk"><span class="mark">☐</span> Đồng ý</p>
                <p class="chk"><span class="mark">☐</span> Ý kiến khác: ………………</p>
            </td>
            <td class="sig">
                <p class="when">………, ngày …… tháng …… năm 20…</p>
                <p><b>Ban Tổng Giám đốc</b></p>
                <p class="hint">(Ký và ghi rõ họ tên)</p>
                <table class="sign-space roomy"><tr><td>&nbsp;</td></tr></table>
            </td>
        </tr>
    </table>

    <p class="foot">Lưu ý: Các trường hợp nghỉ phép chế độ, đính kèm các giấy tờ theo quy định.</p>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont('DejaVu Serif');
            $size = 10;
            $text = '{PAGE_NUM}';
            $width = $fontMetrics->getTextWidth('1', $font, $size);
            $pdf->page_text(($pdf->get_width() - $width) / 2, $pdf->get_height() - 28, $text, $font, $size, [0, 0, 0]);

            $mark = {!! json_encode($watermarkPath ?? '') !!};
            if (is_string($mark) && $mark !== '') {
                $info = @getimagesize($mark);
                $imgW = (int) ($info[0] ?? 1373);
                $imgH = (int) ($info[1] ?? 960);
                $pdf->page_script(function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use ($mark, $imgW, $imgH) {
                    $w = $canvas->get_width();
                    $h = $canvas->get_height();
                    $maxW = $w - 48;
                    $maxH = $h - 48;
                    $scale = min(($w * 0.64) / max(1, $imgW), $maxW / max(1, $imgW), $maxH / max(1, $imgH));
                    $dw = $imgW * $scale;
                    $dh = $imgH * $scale;
                    $canvas->image($mark, ($w - $dw) / 2, ($h - $dh) / 2, $dw, $dh);
                });
            }
        }
    </script>
</body>
</html>
