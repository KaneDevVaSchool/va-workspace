<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Tiêu chí đánh giá</title>
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
    table {
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
    .text-right { text-align: right; font-weight: bold; }
    .status-active { background: #E7F7EE; }
    .status-inactive { background: #FBEAEA; }
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
        <h1>TIÊU CHÍ ĐÁNH GIÁ</h1>
    </div>
    <p class="meta">
        Xuất lúc {{ $generatedAt->format('d/m/Y H:i') }} bởi {{ $exportedBy?->name ?: 'Hệ thống' }}
        &middot; {{ count($rows) }} dòng
    </p>

    <table>
        <thead>
            <tr>
                <th>STT</th>
                <th>Mã loại</th>
                <th>Tên loại tiêu chí</th>
                <th>Tên tiêu chí</th>
                <th>Cách chấm</th>
                <th>Mô tả</th>
                <th>Các mức</th>
                <th>Điểm tối đa</th>
                <th>Trạng thái</th>
                <th>Hiện trên ĐGNL</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr class="{{ $i % 2 === 1 ? 'zebra' : '' }}">
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['type_code'] }}</td>
                    <td>{{ $row['type_name'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['type_label'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['levels_text'] }}</td>
                    <td class="text-right">{{ $row['max_score'] }}</td>
                    <td class="{{ $row['status_label'] === 'Đang áp dụng' ? 'status-active' : 'status-inactive' }}">
                        {{ $row['status_label'] }}
                    </td>
                    <td>{{ $row['use_in_evaluation_label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10">Không có tiêu chí nào phù hợp.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">VA Workspace &middot; Tiêu chí đánh giá</p>
</body>
</html>
