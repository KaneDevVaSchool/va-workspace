<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>{{ $meta['title'] }}</title>
<style>
    @page { margin: 16px 20px 20px; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9px;
        color: #1A1A1A;
        margin: 0;
    }
    h1, h2, h3, p, table { margin: 0; padding: 0; }
    .brand {
        background: #9A0036;
        color: #FFFFFF;
        padding: 10px 14px 9px;
        margin-bottom: 8px;
    }
    .brand .org {
        font-size: 8px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        opacity: 0.85;
        margin-bottom: 2px;
    }
    .brand h1 {
        font-size: 16px;
        letter-spacing: 0.04em;
    }
    .brand .sub {
        font-size: 8.5px;
        margin-top: 3px;
        opacity: 0.9;
    }

    table.form {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        table-layout: fixed;
    }
    table.form td {
        border: 1px solid #E5E5E8;
        padding: 5px 8px 6px;
        vertical-align: top;
        width: 16.66%;
    }
    table.form .k {
        display: block;
        font-size: 7px;
        color: #6B6B6F;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 2px;
    }
    table.form .v {
        font-size: 9.5px;
        font-weight: bold;
    }

    .section {
        font-size: 10px;
        font-weight: bold;
        color: #9A0036;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin: 10px 0 4px;
        padding-bottom: 3px;
        border-bottom: 1.5px solid #9A0036;
    }

    table.matrix {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    }
    table.matrix th,
    table.matrix td {
        border: 1px solid #E5E5E8;
        padding: 4px 5px;
        font-size: 8px;
        vertical-align: top;
    }
    table.matrix thead th {
        background: #9A0036;
        color: #FFFFFF;
        font-weight: bold;
        text-align: center;
    }
    table.matrix thead tr.leaves th {
        background: #7A002B;
        font-size: 7.5px;
        font-weight: normal;
    }
    table.matrix .user {
        text-align: left;
        white-space: nowrap;
    }
    table.matrix .user .email {
        display: block;
        color: #6B6B6F;
        font-size: 7px;
        font-weight: normal;
    }
    table.matrix tbody td { text-align: center; }
    table.matrix tbody td.user { text-align: left; }
    table.matrix tbody tr.zebra td { background: #F7F7F8; }
    table.matrix tfoot td,
    table.matrix tfoot th {
        background: #F3E6EB;
        font-weight: bold;
        text-align: center;
    }
    table.matrix tfoot th.user { text-align: left; }
    .plus { color: #0F7B3A; font-weight: bold; }
    .minus { color: #B42318; font-weight: bold; }
    .warn { color: #B42318; font-size: 7px; display: block; }
    .klass {
        display: inline-block;
        background: #F3E6EB;
        color: #9A0036;
        padding: 1px 5px;
        font-weight: bold;
        font-size: 7.5px;
    }
    .times { font-size: 7px; color: #6B6B6F; }

    .sign {
        width: 100%;
        border-collapse: collapse;
        margin-top: 18px;
    }
    .sign td {
        width: 33.33%;
        text-align: center;
        vertical-align: top;
        padding: 0 10px;
    }
    .sign .place { font-size: 8px; color: #6B6B6F; margin-bottom: 8px; }
    .sign .role { font-weight: bold; font-size: 9.5px; }
    .sign .space { height: 48px; }

    .person { page-break-before: always; }
    .person-head {
        background: #9A0036;
        color: #FFFFFF;
        padding: 9px 12px;
        margin-bottom: 8px;
    }
    .person-head h2 { font-size: 14px; }
    .person-head .email { font-size: 8.5px; opacity: 0.9; margin-top: 2px; }

    table.kpi {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        table-layout: fixed;
    }
    table.kpi td {
        border: 1px solid #E5E5E8;
        padding: 6px 8px;
        width: 33.33%;
        vertical-align: top;
    }
    table.kpi .k {
        display: block;
        font-size: 7px;
        color: #6B6B6F;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 2px;
    }
    table.kpi .v { font-size: 11px; font-weight: bold; }

    table.detail {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    table.detail th,
    table.detail td {
        border: 1px solid #E5E5E8;
        padding: 4px 6px;
        font-size: 8px;
        vertical-align: top;
    }
    table.detail th {
        background: #9A0036;
        color: #FFFFFF;
        text-align: left;
        font-weight: bold;
        font-size: 7.5px;
    }
    table.detail td.num { text-align: right; white-space: nowrap; }
    table.detail tr.zebra td { background: #F7F7F8; }
    table.detail tr.danger td { background: #FBEAEA; }
    table.detail tr.gap td { background: #FFF6E5; }
    table.detail .sub { display: block; color: #6B6B6F; font-size: 7px; }
    .empty { color: #6B6B6F; font-style: italic; padding: 8px 0; }
    .footer {
        font-size: 7.5px;
        color: #6B6B6F;
        text-align: right;
        margin-top: 8px;
    }
</style>
</head>
<body>
    {{-- ========== Trang tổng hợp — form ngang ========== --}}
    <div class="brand">
        <div class="org">{{ $meta['org'] }}@if($meta['department']) · {{ $meta['department'] }}@endif</div>
        <h1>{{ $meta['title'] }}</h1>
        <div class="sub">
            Kỳ {{ $meta['period'] }}
            @if($meta['report_title']) · {{ $meta['report_title'] }}@endif
            @if($meta['revision']) · Bản {{ $meta['revision'] }} ({{ $meta['revision_kind'] }})@endif
        </div>
    </div>

    <table class="form">
        <tr>
            <td>
                <span class="k">Phòng ban</span>
                <span class="v">{{ $meta['department'] !== '' ? $meta['department'] : '—' }}</span>
            </td>
            <td>
                <span class="k">Kỳ đánh giá</span>
                <span class="v">{{ $meta['period'] }}</span>
            </td>
            <td>
                <span class="k">Báo cáo</span>
                <span class="v">{{ $meta['report_title'] !== '' ? $meta['report_title'] : 'Không gắn báo cáo' }}</span>
            </td>
            <td>
                <span class="k">Phiên bản khung</span>
                <span class="v">{{ $meta['version_no'] !== '' ? 'v'.$meta['version_no'] : '—' }}</span>
            </td>
            <td>
                <span class="k">Trạng thái</span>
                <span class="v">{{ $meta['status'] !== '' ? $meta['status'] : '—' }}</span>
            </td>
            <td>
                <span class="k">Cách chấm</span>
                <span class="v">{{ $meta['mode'] }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="k">Số nhân sự</span>
                <span class="v">{{ $meta['people_count'] }}</span>
            </td>
            <td>
                <span class="k">Số tiêu chí hiện</span>
                <span class="v">{{ count($criteria) }}</span>
            </td>
            <td>
                <span class="k">Người xuất</span>
                <span class="v">{{ $meta['exported_by'] }}</span>
            </td>
            <td>
                <span class="k">Thời điểm xuất</span>
                <span class="v">{{ $meta['generated_at'] }}</span>
            </td>
            <td>
                <span class="k">Bản hiển thị</span>
                <span class="v">{{ $meta['revision'] !== '' ? $meta['revision'] : '—' }}</span>
            </td>
            <td>
                <span class="k">Loại bản</span>
                <span class="v">{{ $meta['revision'] !== '' ? $meta['revision_kind'] : '—' }}</span>
            </td>
        </tr>
    </table>

    <div class="section">Bảng tổng hợp cả phòng</div>
    <table class="matrix">
        <thead>
            <tr>
                <th class="user" rowspan="2">Nhân sự</th>
                @if($columns['tasks'])
                    <th rowspan="2">Việc</th>
                @endif
                @php
                    $workSpan = (int) $columns['start_score'] + (int) $columns['task_adjustment'];
                    $resultSpan = (int) $columns['bonus'] + (int) $columns['penalty'] + (int) $columns['final_score'] + (int) $columns['classification'];
                @endphp
                @if($workSpan)
                    <th colspan="{{ $workSpan }}">{{ $headers['work'] }}</th>
                @endif
                @foreach($groups as $group)
                    <th colspan="{{ count($group['items']) }}">{{ $group['label'] }}</th>
                @endforeach
                @if($resultSpan)
                    <th colspan="{{ $resultSpan }}">Kết quả</th>
                @endif
            </tr>
            <tr class="leaves">
                @if($columns['start_score'])<th>{{ $headers['start'] }}</th>@endif
                @if($columns['task_adjustment'])<th>{{ $headers['task_adj'] }}</th>@endif
                @foreach($criteria as $item)
                    <th>{{ $item['name'] }}</th>
                @endforeach
                @if($columns['bonus'])<th>Cộng</th>@endif
                @if($columns['penalty'])<th>Trừ</th>@endif
                @if($columns['final_score'])<th>{{ $headers['final'] }}</th>@endif
                @if($columns['classification'])<th>Xếp loại</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr class="{{ $i % 2 === 1 ? 'zebra' : '' }}">
                    <td class="user">
                        {{ $row['name'] }}
                        @if($row['email'] !== '')
                            <span class="email">{{ $row['email'] }}</span>
                        @endif
                    </td>
                    @if($columns['tasks'])
                        <td>
                            {{ $row['tasks'] }}
                            @if($row['overdue'] > 0)
                                <span class="warn">{{ $row['overdue'] }} trễ</span>
                            @endif
                            @if($row['missing'] > 0)
                                <span class="warn">{{ $row['missing'] }} thiếu điểm</span>
                            @endif
                        </td>
                    @endif
                    @if($columns['start_score'])<td>{{ $row['start'] }}</td>@endif
                    @if($columns['task_adjustment'])
                        <td class="{{ $row['task_adj_class'] }}">{{ $row['task_adj'] }}</td>
                    @endif
                    @foreach($criteria as $item)
                        @php $cell = $row['criteria'][(int) $item['id']] ?? ['text' => '—', 'count' => 0, 'class' => '']; @endphp
                        <td class="{{ $cell['class'] }}">
                            {{ $cell['text'] }}
                            @if(($cell['count'] ?? 0) > 1)
                                <span class="times">×{{ $cell['count'] }}</span>
                            @endif
                        </td>
                    @endforeach
                    @if($columns['bonus'])<td class="{{ $row['bonus_class'] }}">{{ $row['bonus'] }}</td>@endif
                    @if($columns['penalty'])<td class="{{ $row['penalty_class'] }}">{{ $row['penalty'] }}</td>@endif
                    @if($columns['final_score'])<td><strong>{{ $row['final'] }}</strong></td>@endif
                    @if($columns['classification'])
                        <td><span class="klass">{{ $row['klass'] }}</span></td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="20" class="empty">Kỳ này chưa có nhân sự nào để tổng hợp.</td>
                </tr>
            @endforelse
        </tbody>
        @if($foot !== [])
            <tfoot>
                <tr>
                    <th class="user">Trung bình phòng</th>
                    @if($columns['tasks'])<td>{{ $foot['tasks'] }}</td>@endif
                    @if($columns['start_score'])<td>{{ $foot['start'] }}</td>@endif
                    @if($columns['task_adjustment'])
                        <td class="{{ $foot['task_adj_class'] }}">{{ $foot['task_adj'] }}</td>
                    @endif
                    @foreach($criteria as $item)
                        @php $cell = $foot['criteria'][(int) $item['id']] ?? ['text' => '0', 'class' => '']; @endphp
                        <td class="{{ $cell['class'] }}">{{ $cell['text'] }}</td>
                    @endforeach
                    @if($columns['bonus'])<td class="{{ $foot['bonus_class'] }}">{{ $foot['bonus'] }}</td>@endif
                    @if($columns['penalty'])<td class="{{ $foot['penalty_class'] }}">{{ $foot['penalty'] }}</td>@endif
                    @if($columns['final_score'])<td>{{ $foot['final'] }}</td>@endif
                    @if($columns['classification'])<td>{{ $foot['final'] }}</td>@endif
                </tr>
            </tfoot>
        @endif
    </table>

    <table class="sign">
        <tr>
            <td colspan="3" class="place">Ngày {{ now()->format('d') }} tháng {{ now()->format('m') }} năm {{ now()->format('Y') }}</td>
        </tr>
        <tr>
            <td>
                <div class="role">Người lập biểu</div>
                <div class="space"></div>
                <div>{{ $meta['exported_by'] }}</div>
            </td>
            <td>
                <div class="role">Trưởng phòng</div>
                <div class="space"></div>
            </td>
            <td>
                <div class="role">Ban giám đốc</div>
                <div class="space"></div>
            </td>
        </tr>
    </table>
    <p class="footer">VA Workspace · Đánh giá nhân sự</p>

    {{-- ========== Phiếu chi tiết từng nhân sự ========== --}}
    @foreach($people as $person)
        @php $sheet = $person['sheet']; @endphp
        <div class="person">
            <div class="person-head">
                <div class="org" style="font-size:8px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;margin-bottom:2px;">
                    {{ $meta['org'] }} · Phiếu chi tiết nhân sự
                </div>
                <h2>{{ $sheet['name'] }}</h2>
                @if($sheet['email'] !== '')
                    <div class="email">{{ $sheet['email'] }}</div>
                @endif
            </div>

            <table class="kpi">
                @foreach(array_chunk($person['kpis'], 3) as $chunk)
                    <tr>
                        @foreach($chunk as $kpi)
                            <td>
                                <span class="k">{{ $kpi['label'] }}</span>
                                <span class="v">{{ $kpi['value'] }}</span>
                            </td>
                        @endforeach
                        @for($i = count($chunk); $i < 3; $i++)
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            </table>

            <div class="section">Điểm theo tiêu chí</div>
            @if(count($person['criteria']) === 0)
                <p class="empty">Không có tiêu chí nào đang hiện trên bảng.</p>
            @else
                <table class="detail">
                    <thead>
                        <tr>
                            <th style="width:16%">Nhóm</th>
                            <th style="width:22%">Tiêu chí</th>
                            <th style="width:10%" class="num">Điểm / lần</th>
                            <th>Các mức của thang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($person['criteria'] as $i => $line)
                            <tr class="{{ $i % 2 === 1 ? 'zebra' : '' }}">
                                <td>{{ $line['group'] }}</td>
                                <td>{{ $line['name'] }}</td>
                                <td class="num {{ $line['class'] }}">
                                    {{ $line['score'] }}
                                    @if($line['count'] > 1)
                                        <span class="times">×{{ $line['count'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $line['levels'] !== '' ? $line['levels'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="section">Công việc trong kỳ ({{ count($person['tasks']) }})</div>
            @if(count($person['tasks']) === 0)
                <p class="empty">Không có việc nào trong kỳ này.</p>
            @else
                <table class="detail">
                    <thead>
                        <tr>
                            <th>Việc</th>
                            <th style="width:11%">Trạng thái</th>
                            <th style="width:10%">Tiến độ</th>
                            <th style="width:9%">Hạn nộp</th>
                            <th style="width:9%">Ngày xong</th>
                            @if($weighted)
                                <th style="width:6%" class="num">Độ khó</th>
                                <th style="width:7%" class="num">Hệ số TĐ</th>
                                <th style="width:7%" class="num">Chất lượng</th>
                                <th style="width:7%" class="num">Điểm chuẩn</th>
                                <th style="width:7%" class="num">Điểm thực</th>
                                <th style="width:14%">Ghi chú</th>
                            @else
                                <th style="width:8%" class="num">Điểm việc</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($person['tasks'] as $i => $task)
                            <tr class="{{ $task['overdue'] ? 'danger' : ($task['gap'] ? 'gap' : ($i % 2 === 1 ? 'zebra' : '')) }}">
                                <td>
                                    {{ $task['title'] }}
                                    @if($task['project'] !== '')
                                        <span class="sub">{{ $task['project'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $task['status'] }}</td>
                                <td>{{ $task['timeliness'] }}</td>
                                <td>{{ $task['end'] }}</td>
                                <td>{{ $task['actual'] }}</td>
                                @if($weighted)
                                    <td class="num">{{ $task['difficulty'] }}</td>
                                    <td class="num">{{ $task['progress'] }}</td>
                                    <td class="num">{{ $task['quality'] }}</td>
                                    <td class="num">{{ $task['standard'] }}</td>
                                    <td class="num">{{ $task['actual_score'] }}</td>
                                    <td>{{ $task['note'] !== '' ? $task['note'] : '—' }}</td>
                                @else
                                    <td class="num">{{ $task['contribution'] }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="section">Ghi nhận đánh giá ({{ count($person['events']) }})</div>
            @if(count($person['events']) === 0)
                <p class="empty">Chưa ghi nhận lần nào trong kỳ này.</p>
            @else
                <table class="detail">
                    <thead>
                        <tr>
                            <th style="width:9%">Ngày</th>
                            <th style="width:18%">Tiêu chí</th>
                            <th style="width:14%">Nhóm</th>
                            <th style="width:14%">Mức</th>
                            <th style="width:7%" class="num">Điểm</th>
                            <th style="width:16%">Việc gắn</th>
                            <th style="width:12%">Người ghi nhận</th>
                            <th>Lý do</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($person['events'] as $i => $event)
                            <tr class="{{ $i % 2 === 1 ? 'zebra' : '' }}">
                                <td>{{ $event['date'] }}</td>
                                <td>{{ $event['criterion'] }}</td>
                                <td>{{ $event['group'] !== '' ? $event['group'] : '—' }}</td>
                                <td>{{ $event['level'] !== '' ? $event['level'] : '—' }}</td>
                                <td class="num {{ $event['class'] }}">{{ $event['score'] }}</td>
                                <td>{{ $event['task'] }}</td>
                                <td>{{ $event['recorder'] }}</td>
                                <td>{{ $event['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <p class="footer">VA Workspace · {{ $sheet['name'] }} · {{ $meta['period'] }}</p>
        </div>
    @endforeach
</body>
</html>
