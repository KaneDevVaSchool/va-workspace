<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>{{ $documentCode }} - Đơn nghỉ phép</title>
<style>
    @page { margin: 12mm 12mm 10mm 14mm; }
    body {
        margin: 0;
        color: #000000;
        font-family: 'DejaVu Serif', serif;
        font-size: 11px;
        line-height: 1.3;
    }
    table { width: 100%; border-collapse: collapse; }
    .head { border: 1px solid #000000; margin-bottom: 10px; }
    .head td { border: 1px solid #000000; vertical-align: middle; }
    .logo { width: 22%; text-align: center; padding: 6px 4px; }
    .logo img { width: 68px; height: auto; }
    .title { width: 48%; text-align: center; padding: 8px 6px; }
    .title h1 {
        margin: 0;
        font-size: 16px;
        font-weight: normal;
        letter-spacing: 0.02em;
        line-height: 1.25;
    }
    .meta { width: 30%; padding: 6px 8px; font-size: 11px; line-height: 1.45; }
    .meta b { font-weight: bold; }
    p { margin: 0 0 2px; }
    .gap { height: 3px; }
    b { font-weight: bold; }
    .kgu { font-style: italic; text-decoration: underline; }
    .right { text-align: right; }
    .center { text-align: center; }
    .sign-space { height: 22px; }
    .hint { font-style: italic; font-size: 11px; }
    .opinions { margin-top: 8px; border-top: 1px solid #000000; }
    .opinions td { width: 50%; vertical-align: top; padding: 4px 6px 2px; }
    .opinion-title { font-weight: bold; text-decoration: underline; font-size: 13px; margin: 0 0 6px; }
    .foot { margin-top: 8px; font-size: 11px; }
    .box { font-family: 'DejaVu Sans', sans-serif; }
</style>
</head>
<body>
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
                Kí hiệu: <b>{{ $formSymbol }}</b><br>
                Ngày hiệu lực: {{ $effectiveDate }}<br>
                Lần ban hành: {{ $revision }}<br>
                Mã đơn: <b>{{ $documentCode }}</b>
            </td>
        </tr>
    </table>

    <p><span class="kgu">Kính gửi:</span> - {{ $recipientTitle }}@if ($recipientName !== ''): {{ $recipientName }}@endif</p>
    <p><b>Phòng Hành chính Nhân sự</b></p>
    <div class="gap"></div>

    <p>Tôi tên: <b>{{ $employeeName }}</b> &nbsp;&nbsp;&nbsp; Mã nhân viên: <b>{{ $employeeCode }}</b></p>
    <p>Chức danh: <b>{{ $jobTitle }}</b> &nbsp;&nbsp;&nbsp; Đơn vị/Phòng ban: <b>{{ $workplace }}</b></p>
    <p>Ngày vào nhận việc: <b>{{ $hiredAt }}</b> &nbsp;&nbsp;&nbsp; Ngày chính thức: <b>{{ $officialAt }}</b></p>
    <div class="gap"></div>

    <p>Nay tôi làm đơn này kính xin Ban lãnh đạo chấp thuận cho tôi được nghỉ phép.</p>
    <p>Thời gian: từ <b>{{ $dateFromDisplay }}</b> đến <b>{{ $dateToDisplay }}</b></p>
    <p>Chi tiết: <b>{{ $detailLabel }}</b> &nbsp;&nbsp; Buổi: <b>{{ $sessionLabel }}</b> &nbsp;&nbsp; Số ngày công: <b>{{ $totalDaysLabel }}</b> ngày</p>
    <p>Loại nghỉ: <b>{{ $leaveTypeLabel }}</b></p>
    <p>
        @foreach ($buckets as $bucket)
            <span class="box">{{ $bucket['checked'] ? '☑' : '☐' }}</span> {{ $bucket['label'] }}; Số ngày: <b>{{ $bucket['days'] }}</b> ngày
            @if (! $loop->last)
                &nbsp;&nbsp;
            @endif
        @endforeach
    </p>
    <p>Lý do: <b>{{ $reason }}</b></p>
    <p>Ghi chú duyệt: <b>{{ $approvalNote ?: '………………' }}</b></p>
    <div class="gap"></div>
    <p>Tôi cam kết quay lại làm việc sau khi kết thúc thời gian nghỉ phép.</p>
    <div class="gap"></div>

    <p class="right">{{ $employeeSignDate }}</p>
    <p class="right"><b>Người làm đơn</b></p>
    <p class="right hint">(Ký và ghi rõ họ tên)</p>
    <div class="sign-space"></div>
    <p class="right"><b>{{ $employeeName }}</b></p>

    <table class="opinions">
        <tr>
            <td>
                <p class="opinion-title">Ý kiến của Trưởng đơn vị</p>
                <p>Ghi chú duyệt: {{ $approvalNote ?: '………………' }}</p>
            </td>
            <td class="center">
                <p>{{ $approverSignDate }}</p>
                <p><b>Trưởng đơn vị</b></p>
                <p class="hint">(Ký và ghi rõ họ tên)</p>
                <div class="sign-space"></div>
                <p><b>{{ $approverName }}</b></p>
            </td>
        </tr>
        <tr>
            <td>
                <p class="opinion-title">Ý kiến Phòng HCNS</p>
                <p>Ngày nhận đơn: ………………</p>
            </td>
            <td class="center">
                <p class="hint">………, ngày …… tháng …… năm 20…</p>
                <p><b>Trưởng phòng/ Giám đốc HCNS</b></p>
                <p class="hint">(Ký và ghi rõ họ tên)</p>
                <div class="sign-space"></div>
            </td>
        </tr>
        <tr>
            <td>
                <p class="opinion-title">Phê duyệt của TGĐ</p>
                <p>☐ Đồng ý &nbsp;&nbsp; ☐ Ý kiến khác: ………………</p>
            </td>
            <td class="center">
                <p class="hint">………, ngày …… tháng …… năm 20…</p>
                <p><b>Ban Tổng Giám đốc</b></p>
                <p class="hint">(Ký và ghi rõ họ tên)</p>
                <div class="sign-space"></div>
            </td>
        </tr>
    </table>

    <p class="foot">Lưu ý: Các trường hợp nghỉ phép chế độ, đính kèm các giấy tờ theo quy định.</p>
</body>
</html>
