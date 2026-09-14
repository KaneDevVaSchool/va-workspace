<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Báo cáo chi phí tài khoản dịch vụ</title>
<style>
    @page { margin: 18px 24px; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10px;
        color: #1A1A1A;
        margin: 0;
    }
    .header {
        background: #9A0036;
        color: #FFFFFF;
        padding: 10px 14px;
        border-radius: 4px;
        margin-bottom: 6px;
    }
    .header h1 {
        margin: 0;
        font-size: 16px;
    }
    .meta {
        color: #6B6B6F;
        font-size: 9px;
        margin: 0 0 10px;
    }
    .summary {
        width: 100%;
        margin-bottom: 12px;
    }
    .summary td {
        width: 33.33%;
        padding: 8px 10px;
        border: 1px solid #E5E5E8;
        background: #F7F7F8;
    }
    .summary-label {
        display: block;
        color: #6B6B6F;
        font-size: 9px;
        margin-bottom: 3px;
    }
    .summary-value {
        display: block;
        color: #9A0036;
        font-size: 15px;
        font-weight: bold;
    }
    table.data {
        width: 100%;
        border-collapse: collapse;
    }
    thead th {
        background: #9A0036;
        color: #FFFFFF;
        font-size: 9px;
        text-align: left;
        padding: 5px 6px;
        border: 1px solid #E5E5E8;
    }
    tbody td {
        font-size: 9px;
        padding: 5px 6px;
        border: 1px solid #E5E5E8;
        vertical-align: top;
    }
    tbody tr.zebra { background: #F7F7F8; }
    .text-right { text-align: right; }
    .footer {
        margin-top: 8px;
        font-size: 8px;
        color: #6B6B6F;
        text-align: right;
    }
</style>
</head>
<body>
    <div class="header">
        <h1>BÁO CÁO CHI PHÍ TÀI KHOẢN DỊCH VỤ</h1>
    </div>
    <p class="meta">
        Xuất lúc {{ $generatedAt->format('d/m/Y H:i') }} bởi {{ $exportedBy?->name ?: 'Hệ thống' }}
        &middot; {{ count($rows) }} tài khoản đang tính chi phí
    </p>

    <table class="summary">
        <tr>
            <td>
                <span class="summary-label">Tổng chi phí mỗi tháng</span>
                <span class="summary-value">{{ number_format($summary['total_monthly'], 0, ',', '.') }} đ</span>
            </td>
            <td>
                <span class="summary-label">Ước tính mỗi năm</span>
                <span class="summary-value">{{ number_format($summary['total_yearly_estimate'], 0, ',', '.') }} đ</span>
            </td>
            <td>
                <span class="summary-label">Số tài khoản đang tính phí</span>
                <span class="summary-value">{{ $summary['account_count'] }}</span>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>STT</th>
                <th>Tên</th>
                <th>Nhà cung cấp</th>
                <th>Loại tài khoản</th>
                <th>Chi phí/tháng (VNĐ)</th>
                <th>Ngày mua</th>
                <th>Ngày hết hạn</th>
                <th>Trạng thái</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr class="{{ $i % 2 === 1 ? 'zebra' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['provider'] }}</td>
                    <td>{{ $row['account_type'] }}</td>
                    <td class="text-right">{{ number_format($row['monthly_cost_vnd'], 0, ',', '.') }}</td>
                    <td>{{ $row['purchased_at'] ? \Illuminate\Support\Carbon::parse($row['purchased_at'])->format('d/m/Y') : '—' }}</td>
                    <td>{{ $row['expires_at'] ? \Illuminate\Support\Carbon::parse($row['expires_at'])->format('d/m/Y') : '—' }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Chưa có tài khoản nào tính chi phí.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">VA Workspace &middot; Báo cáo chi phí tài khoản dịch vụ</p>
</body>
</html>
