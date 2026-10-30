<?php

namespace Tests\Unit\Attendance;

use Modules\Attendance\App\Services\EmployeeLeaveFormService;
use Tests\TestCase;

class EmployeeLeaveFormTest extends TestCase
{
    public function test_leave_form_fills_the_official_template(): void
    {
        config(['database.connections.hrm.database' => null]);

        $service = app(EmployeeLeaveFormService::class);
        $data = $service->formData([
            'id' => 8,
            'status' => 'approved',
            'employee_name' => 'Phạm Quang Thái',
            'employee_code' => 'VA011276',
            'job_title' => 'Nhân viên IT',
            'department_name' => 'Phòng Công Nghệ',
            'hired_at' => '2025-10-01',
            'official_date' => '2025-12-01',
            'leave_type_code' => 'PN',
            'leave_type_name' => 'Nghỉ phép năm',
            'sort_order' => 1,
            'balance_quota' => 'annual',
            'mode' => 'day',
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-10',
            'total_days' => 0.5,
            'reason' => 'Em xin phép off về quê có việc gia đình ạ',
            'submitted_at' => '2026-10-07 09:00:00',
            'decided_at' => '2026-10-19 14:00:00',
            'approver' => [
                'name' => 'Nguyễn Viết Hùng',
                'job_title' => 'Trưởng Bộ phận Công nghệ Thông tin',
            ],
        ], [
            'full_name' => 'Phạm Quang Thái',
            'code' => 'VA011276',
            'job_title' => 'Nhân viên IT',
            'department_name' => 'Phòng Công Nghệ',
            'hired_at' => '2025-10-01',
        ]);
        $data['logoPath'] = str_replace('\\', '/', module_path('Attendance', 'resources/images/va-form-logo.png'));

        $html = view('attendance::pdf.leave-form', $data)->render();

        $this->assertSame('NP-2610-0008', $data['documentCode']);
        $this->assertStringContainsString('BM.01/HN.QT.02', $html);
        $this->assertStringContainsString('va-form-logo.png', $html);
        $this->assertStringContainsString('Phạm Quang Thái', $html);
        $this->assertStringContainsString('VA011276', $html);
        $this->assertStringContainsString('Nhân viên IT', $html);
        $this->assertStringContainsString('Đơn vị/Phòng ban', $html);
        $this->assertStringContainsString('Phòng Công Nghệ', $html);
        $this->assertStringContainsString('01/10/2025', $html);
        $this->assertStringContainsString('01/12/2025', $html);
        $this->assertStringContainsString('từ', $html);
        $this->assertStringContainsString('10/10/2026', $html);
        $this->assertStringContainsString('10/10/2026 (07:30-17:00)', $html);
        $this->assertStringContainsString('07:30-17:00', $html);
        $this->assertStringContainsString('Cả ngày', $html);
        $this->assertStringContainsString('0.50', $html);
        $this->assertStringContainsString('1. Nghỉ phép năm (PN)', $html);
        $this->assertStringContainsString('☑', $html);
        $this->assertStringContainsString('Phép năm', $html);
        $this->assertStringContainsString('☐', $html);
        $this->assertStringContainsString('Nguyễn Viết Hùng', $html);
        $this->assertStringContainsString('Trưởng Bộ phận Công nghệ Thông tin', $html);
        $this->assertStringContainsString('Ngày 07 tháng 10 năm 2026', $html);
        $this->assertStringContainsString('Ngày 19 tháng 10 năm 2026', $html);
        $this->assertStringContainsString('Em xin phép off về quê có việc gia đình ạ', $html);

        $hour = $service->formData([
            'id' => 9,
            'date_from' => '2026-10-10',
            'mode' => 'hour',
            'time_from' => '08:15:00',
            'time_to' => '10:45',
            'session' => 'morning',
            'leave_type_code' => 'KL',
            'total_days' => 0.25,
        ]);
        $this->assertSame('10/10/2026 (08:15-10:45)', $hour['detailLabel']);
        $this->assertTrue($hour['buckets'][1]['checked']);
        $this->assertSame('0.25', $hour['buckets'][1]['days']);
        $this->assertFalse($hour['buckets'][0]['checked']);

        $morning = $service->formData([
            'date_from' => '2026-10-10',
            'mode' => 'day',
            'session' => 'morning',
            'leave_type_code' => 'TS',
            'total_days' => 0.5,
        ]);
        $this->assertSame('10/10/2026 (07:30-12:00)', $morning['detailLabel']);
        $this->assertTrue($morning['buckets'][2]['checked']);

        $binary = \Barryvdh\DomPDF\Facade\Pdf::loadView('attendance::pdf.leave-form', $data)
            ->setPaper('a4', 'portrait')
            ->output();
        $this->assertStringStartsWith('%PDF', $binary);
    }
}
