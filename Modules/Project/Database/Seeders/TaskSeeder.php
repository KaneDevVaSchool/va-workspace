<?php

namespace Modules\Project\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Services\TaskService;

/**
 * Công việc demo — cây WBS đa cấp trên các dự án của ProjectSeeder, trải
 * đều loại (task/phase/category), trạng thái, mức ưu tiên, người thực hiện
 * để trang "Tất cả công việc" (list + kanban + tab đếm + panel chi tiết)
 * có dữ liệu thật ngay.
 *
 * Idempotent: bỏ qua dự án đã có Task. ProjectSeeder / DemoUserSeeder phải
 * chạy trước — xem DatabaseSeeder::run().
 */
class TaskSeeder extends Seeder
{
    private int $codeSeq = 1;

    public function run(): void
    {
        $creator = User::query()->orderBy('id')->first();
        if ($creator === null) {
            return;
        }

        $users = User::query()->get()->keyBy('email');
        $service = app(TaskService::class);

        foreach ($this->trees() as $projectName => $nodes) {
            $project = Project::query()->where('name', $projectName)->first();
            if ($project === null) {
                continue;
            }

            if (Task::query()->where('project_id', $project->id)->exists()) {
                continue;
            }

            $this->seedNodes($service, $project, $creator, $users, $nodes, null);
        }
    }

    /**
     * @return array<string, list<array<string, mixed>>>  tên dự án => cây node
     */
    private function trees(): array
    {
        return [
            'Triển khai hệ thống quản lý đào tạo trực tuyến' => [
                [
                    'title' => 'Khảo sát & thiết kế',
                    'type' => 'phase',
                    'status' => 'completed',
                    'priority' => 'high',
                    'start_date' => now()->subDays(45)->toDateString(),
                    'end_date' => now()->subDays(20)->toDateString(),
                    'actual_start_date' => now()->subDays(45)->toDateString(),
                    'actual_end_date' => now()->subDays(22)->toDateString(),
                    'progress_percent' => 100,
                    'description' => 'Thu thập yêu cầu và chốt kiến trúc trước khi phát triển.',
                    'children' => [
                        [
                            'title' => 'Thu thập yêu cầu nghiệp vụ',
                            'status' => 'completed',
                            'priority' => 'high',
                            'assignee_email' => 'duc.cntt@example.com',
                            'start_date' => now()->subDays(45)->toDateString(),
                            'end_date' => now()->subDays(35)->toDateString(),
                            'actual_start_date' => now()->subDays(45)->toDateString(),
                            'actual_end_date' => now()->subDays(36)->toDateString(),
                            'progress_percent' => 100,
                            'description' => 'Phỏng vấn Đào tạo, chốt use-case học viên / giảng viên.',
                        ],
                        [
                            'title' => 'Thiết kế kiến trúc hệ thống',
                            'status' => 'completed',
                            'priority' => 'high',
                            'assignee_email' => 'linh.cntt@example.com',
                            'start_date' => now()->subDays(36)->toDateString(),
                            'end_date' => now()->subDays(25)->toDateString(),
                            'actual_start_date' => now()->subDays(36)->toDateString(),
                            'actual_end_date' => now()->subDays(24)->toDateString(),
                            'progress_percent' => 100,
                        ],
                        [
                            'title' => 'Phê duyệt mockup giao diện',
                            'status' => 'completed',
                            'priority' => 'medium',
                            'assignee_email' => ':creator',
                            'start_date' => now()->subDays(24)->toDateString(),
                            'end_date' => now()->subDays(20)->toDateString(),
                            'actual_start_date' => now()->subDays(24)->toDateString(),
                            'actual_end_date' => now()->subDays(22)->toDateString(),
                            'progress_percent' => 100,
                        ],
                    ],
                ],
                [
                    'title' => 'Phát triển',
                    'type' => 'phase',
                    'status' => 'in_progress',
                    'priority' => 'urgent',
                    'start_date' => now()->subDays(20)->toDateString(),
                    'end_date' => now()->addDays(5)->toDateString(),
                    'actual_start_date' => now()->subDays(20)->toDateString(),
                    'progress_percent' => 45,
                    'description' => 'Xây dựng các module học viên và giảng viên.',
                    'children' => [
                        [
                            'title' => 'Module học viên',
                            'type' => 'category',
                            'status' => 'in_progress',
                            'priority' => 'high',
                            'progress_percent' => 50,
                            'children' => [
                                [
                                    'title' => 'Màn hình đăng ký khóa học',
                                    'status' => 'in_progress',
                                    'priority' => 'high',
                                    'assignee_email' => 'duc.cntt@example.com',
                                    'start_date' => now()->subDays(18)->toDateString(),
                                    'end_date' => now()->addDays(2)->toDateString(),
                                    'actual_start_date' => now()->subDays(18)->toDateString(),
                                    'progress_percent' => 70,
                                    'description' => 'Form đăng ký, lịch học, xác nhận email.',
                                ],
                                [
                                    'title' => 'Phát video bài giảng',
                                    'status' => 'in_progress',
                                    'priority' => 'medium',
                                    'assignee_email' => 'linh.cntt@example.com',
                                    'start_date' => now()->subDays(12)->toDateString(),
                                    'end_date' => now()->addDays(8)->toDateString(),
                                    'actual_start_date' => now()->subDays(10)->toDateString(),
                                    'progress_percent' => 35,
                                ],
                            ],
                        ],
                        [
                            'title' => 'Module giảng viên',
                            'type' => 'category',
                            'status' => 'not_started',
                            'priority' => 'medium',
                            'progress_percent' => 0,
                            'children' => [
                                [
                                    'title' => 'Quản lý điểm danh',
                                    'status' => 'not_started',
                                    'priority' => 'medium',
                                    'assignee_email' => 'duc.cntt@example.com',
                                    'start_date' => now()->addDays(3)->toDateString(),
                                    'end_date' => now()->addDays(12)->toDateString(),
                                    'progress_percent' => 0,
                                ],
                                [
                                    'title' => 'Nhập điểm và xuất bảng điểm',
                                    'status' => 'not_started',
                                    'priority' => 'low',
                                    'start_date' => now()->addDays(8)->toDateString(),
                                    'end_date' => now()->addDays(15)->toDateString(),
                                    'progress_percent' => 0,
                                    'description' => 'Chưa giao người — chờ xong điểm danh.',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Kiểm thử UAT với Đào tạo',
                    'status' => 'not_started',
                    'priority' => 'high',
                    'assignee_email' => ':creator',
                    'start_date' => now()->addDays(8)->toDateString(),
                    'end_date' => now()->addDays(15)->toDateString(),
                    'progress_percent' => 0,
                    'description' => 'Công việc gốc (không nằm trong phase) — bàn giao thử nghiệm.',
                ],
            ],

            'Nâng cấp hạ tầng mạng nội bộ' => [
                [
                    'title' => 'Khảo sát hiện trạng',
                    'type' => 'phase',
                    'status' => 'completed',
                    'priority' => 'high',
                    'start_date' => now()->subDays(20)->toDateString(),
                    'end_date' => now()->subDays(12)->toDateString(),
                    'actual_start_date' => now()->subDays(20)->toDateString(),
                    'actual_end_date' => now()->subDays(13)->toDateString(),
                    'progress_percent' => 100,
                    'children' => [
                        [
                            'title' => 'Kiểm kê switch và điểm truy cập',
                            'status' => 'completed',
                            'priority' => 'high',
                            'assignee_email' => 'duc.cntt@example.com',
                            'start_date' => now()->subDays(20)->toDateString(),
                            'end_date' => now()->subDays(15)->toDateString(),
                            'actual_start_date' => now()->subDays(20)->toDateString(),
                            'actual_end_date' => now()->subDays(15)->toDateString(),
                            'progress_percent' => 100,
                        ],
                        [
                            'title' => 'Đo băng thông theo khu vực',
                            'status' => 'completed',
                            'priority' => 'medium',
                            'assignee_email' => 'linh.cntt@example.com',
                            'start_date' => now()->subDays(16)->toDateString(),
                            'end_date' => now()->subDays(12)->toDateString(),
                            'actual_start_date' => now()->subDays(16)->toDateString(),
                            'actual_end_date' => now()->subDays(13)->toDateString(),
                            'progress_percent' => 100,
                        ],
                    ],
                ],
                [
                    'title' => 'Triển khai thiết bị',
                    'type' => 'phase',
                    'status' => 'in_progress',
                    'priority' => 'urgent',
                    'start_date' => now()->subDays(10)->toDateString(),
                    'end_date' => now()->addDays(20)->toDateString(),
                    'actual_start_date' => now()->subDays(10)->toDateString(),
                    'progress_percent' => 40,
                    'children' => [
                        [
                            'title' => 'Thay switch khu A',
                            'status' => 'in_progress',
                            'priority' => 'urgent',
                            'assignee_email' => 'duc.cntt@example.com',
                            'start_date' => now()->subDays(10)->toDateString(),
                            'end_date' => now()->addDays(3)->toDateString(),
                            'actual_start_date' => now()->subDays(9)->toDateString(),
                            'progress_percent' => 55,
                            'description' => 'Cắt mạng ngoài giờ — phối hợp Đào tạo.',
                        ],
                        [
                            'title' => 'Lịch cắt mạng khu Đào tạo',
                            'status' => 'on_hold',
                            'priority' => 'high',
                            'assignee_email' => 'tuan.dt@example.com',
                            'start_date' => now()->subDays(5)->toDateString(),
                            'end_date' => now()->addDays(10)->toDateString(),
                            'progress_percent' => 20,
                            'description' => 'Tạm dừng chờ lịch thi giữa kỳ.',
                        ],
                        [
                            'title' => 'Phối hợp bố trí nhân sự trực',
                            'status' => 'not_started',
                            'priority' => 'medium',
                            'assignee_email' => 'son.ns@example.com',
                            'start_date' => now()->addDays(5)->toDateString(),
                            'end_date' => now()->addDays(18)->toDateString(),
                            'progress_percent' => 0,
                        ],
                    ],
                ],
                [
                    'title' => 'Viết tài liệu vận hành',
                    'status' => 'not_started',
                    'priority' => 'low',
                    'assignee_email' => 'linh.cntt@example.com',
                    'start_date' => now()->addDays(25)->toDateString(),
                    'end_date' => now()->addDays(40)->toDateString(),
                    'progress_percent' => 0,
                ],
            ],

            'Số hoá hồ sơ nhân sự' => [
                [
                    'title' => 'Chuẩn bị',
                    'type' => 'category',
                    'status' => 'not_started',
                    'priority' => 'medium',
                    'progress_percent' => 0,
                    'children' => [
                        [
                            'title' => 'Rà soát hồ sơ giấy hiện có',
                            'status' => 'not_started',
                            'priority' => 'high',
                            'assignee_email' => 'thao.ns@example.com',
                            'start_date' => now()->addDays(10)->toDateString(),
                            'end_date' => now()->addDays(30)->toDateString(),
                            'progress_percent' => 0,
                            'description' => 'Phân loại hợp đồng, quyết định, bảng lương lưu kho.',
                        ],
                        [
                            'title' => 'Chọn phần mềm lưu trữ điện tử',
                            'status' => 'not_started',
                            'priority' => 'medium',
                            'assignee_email' => 'son.ns@example.com',
                            'start_date' => now()->addDays(12)->toDateString(),
                            'end_date' => now()->addDays(25)->toDateString(),
                            'progress_percent' => 0,
                        ],
                    ],
                ],
                [
                    'title' => 'Tập huấn nhập liệu cho nhân sự',
                    'status' => 'not_started',
                    'priority' => 'low',
                    'assignee_email' => 'son.ns@example.com',
                    'start_date' => now()->addDays(40)->toDateString(),
                    'end_date' => now()->addDays(50)->toDateString(),
                    'progress_percent' => 0,
                ],
            ],

            'Kiểm toán tài chính năm học 2025-2026' => [
                [
                    'title' => 'Soát xét sổ sách',
                    'type' => 'phase',
                    'status' => 'completed',
                    'priority' => 'high',
                    'start_date' => now()->subDays(90)->toDateString(),
                    'end_date' => now()->subDays(40)->toDateString(),
                    'actual_start_date' => now()->subDays(90)->toDateString(),
                    'actual_end_date' => now()->subDays(42)->toDateString(),
                    'progress_percent' => 100,
                    'children' => [
                        [
                            'title' => 'Đối chiếu công nợ nhà cung cấp',
                            'status' => 'completed',
                            'priority' => 'high',
                            'assignee_email' => 'phong.tc@example.com',
                            'start_date' => now()->subDays(90)->toDateString(),
                            'end_date' => now()->subDays(60)->toDateString(),
                            'actual_start_date' => now()->subDays(90)->toDateString(),
                            'actual_end_date' => now()->subDays(58)->toDateString(),
                            'progress_percent' => 100,
                        ],
                        [
                            'title' => 'Trích xuất dữ liệu hệ thống',
                            'status' => 'completed',
                            'priority' => 'medium',
                            'assignee_email' => 'duc.cntt@example.com',
                            'start_date' => now()->subDays(70)->toDateString(),
                            'end_date' => now()->subDays(55)->toDateString(),
                            'actual_start_date' => now()->subDays(70)->toDateString(),
                            'actual_end_date' => now()->subDays(56)->toDateString(),
                            'progress_percent' => 100,
                        ],
                        [
                            'title' => 'Đối chiếu số liệu học phí',
                            'status' => 'completed',
                            'priority' => 'high',
                            'assignee_email' => 'tuan.dt@example.com',
                            'start_date' => now()->subDays(55)->toDateString(),
                            'end_date' => now()->subDays(40)->toDateString(),
                            'actual_start_date' => now()->subDays(55)->toDateString(),
                            'actual_end_date' => now()->subDays(42)->toDateString(),
                            'progress_percent' => 100,
                        ],
                    ],
                ],
                [
                    'title' => 'Lập báo cáo kiểm toán nội bộ',
                    'status' => 'completed',
                    'priority' => 'urgent',
                    'assignee_email' => 'yen.tc@example.com',
                    'start_date' => now()->subDays(40)->toDateString(),
                    'end_date' => now()->subDays(10)->toDateString(),
                    'actual_start_date' => now()->subDays(38)->toDateString(),
                    'actual_end_date' => now()->subDays(12)->toDateString(),
                    'progress_percent' => 100,
                    'description' => 'Báo cáo đã trình Ban Giám hiệu.',
                ],
            ],

            'Ngày hội tuyển sinh 2026' => [
                [
                    'title' => 'Chuẩn bị sự kiện',
                    'type' => 'phase',
                    'status' => 'in_progress',
                    'priority' => 'urgent',
                    'start_date' => now()->subDays(5)->toDateString(),
                    'end_date' => now()->addDays(25)->toDateString(),
                    'actual_start_date' => now()->subDays(5)->toDateString(),
                    'progress_percent' => 40,
                    'children' => [
                        [
                            'title' => 'Gian hàng tư vấn ngành học',
                            'status' => 'in_progress',
                            'priority' => 'urgent',
                            'assignee_email' => 'tuan.dt@example.com',
                            'start_date' => now()->subDays(5)->toDateString(),
                            'end_date' => now()->addDays(20)->toDateString(),
                            'actual_start_date' => now()->subDays(4)->toDateString(),
                            'progress_percent' => 50,
                        ],
                        [
                            'title' => 'Backdrop và standee',
                            'status' => 'completed',
                            'priority' => 'medium',
                            'assignee_email' => 'mai.dt@example.com',
                            'start_date' => now()->subDays(5)->toDateString(),
                            'end_date' => now()->subDays(1)->toDateString(),
                            'actual_start_date' => now()->subDays(5)->toDateString(),
                            'actual_end_date' => now()->subDays(1)->toDateString(),
                            'progress_percent' => 100,
                        ],
                        [
                            'title' => 'Hệ thống đăng ký online',
                            'status' => 'in_progress',
                            'priority' => 'high',
                            'assignee_email' => 'duc.cntt@example.com',
                            'start_date' => now()->subDays(4)->toDateString(),
                            'end_date' => now()->addDays(10)->toDateString(),
                            'actual_start_date' => now()->subDays(3)->toDateString(),
                            'progress_percent' => 40,
                        ],
                        [
                            'title' => 'Phân công nhân sự đón tiếp',
                            'status' => 'in_progress',
                            'priority' => 'high',
                            'assignee_email' => 'son.ns@example.com',
                            'start_date' => now()->subDays(2)->toDateString(),
                            'end_date' => now()->addDays(15)->toDateString(),
                            'progress_percent' => 25,
                        ],
                        [
                            'title' => 'Ngân sách hậu cần',
                            'status' => 'on_hold',
                            'priority' => 'medium',
                            'assignee_email' => 'thao.ns@example.com',
                            'start_date' => now()->toDateString(),
                            'end_date' => now()->addDays(12)->toDateString(),
                            'progress_percent' => 10,
                            'description' => 'Chờ xác nhận hạn mức từ Tài chính.',
                        ],
                        [
                            'title' => 'Kịch bản khai mạc',
                            'status' => 'not_started',
                            'priority' => 'medium',
                            'assignee_email' => 'hoa.bgh@example.com',
                            'start_date' => now()->addDays(5)->toDateString(),
                            'end_date' => now()->addDays(18)->toDateString(),
                            'progress_percent' => 0,
                        ],
                        [
                            'title' => 'Livestream ngày hội',
                            'status' => 'not_started',
                            'priority' => 'low',
                            'assignee_email' => 'linh.cntt@example.com',
                            'start_date' => now()->addDays(20)->toDateString(),
                            'end_date' => now()->addDays(28)->toDateString(),
                            'progress_percent' => 0,
                        ],
                    ],
                ],
                [
                    'title' => 'Tổng kết sau sự kiện',
                    'status' => 'not_started',
                    'priority' => 'low',
                    'assignee_email' => ':creator',
                    'start_date' => now()->addDays(28)->toDateString(),
                    'end_date' => now()->addDays(30)->toDateString(),
                    'progress_percent' => 0,
                    'description' => 'Báo cáo số lượng phụ huynh, tỉ lệ đăng ký.',
                ],
            ],

            'Xây dựng chương trình khách hàng thân thiết' => [
                [
                    'title' => 'Đề xuất ngân sách marketing',
                    'status' => 'on_hold',
                    'priority' => 'high',
                    'assignee_email' => 'mai.dt@example.com',
                    'start_date' => now()->subDays(5)->toDateString(),
                    'end_date' => now()->addDays(20)->toDateString(),
                    'progress_percent' => 15,
                    'description' => 'Tạm dừng chờ phê duyệt ngân sách quý tới.',
                ],
                [
                    'title' => 'Thiết kế thẻ thành viên',
                    'status' => 'on_hold',
                    'priority' => 'medium',
                    'assignee_email' => 'mai.dt@example.com',
                    'start_date' => now()->addDays(10)->toDateString(),
                    'end_date' => now()->addDays(40)->toDateString(),
                    'progress_percent' => 0,
                ],
                [
                    'title' => 'Khảo sát đối thủ cùng ngành',
                    'status' => 'not_started',
                    'priority' => 'low',
                    'start_date' => now()->addDays(20)->toDateString(),
                    'end_date' => now()->addDays(50)->toDateString(),
                    'progress_percent' => 0,
                ],
            ],

            // Dự án riêng để demo Lịch công việc (chế độ xem theo tuần) khi
            // nhiều công việc/nhiều phòng ban chồng giờ trong cùng 1 ngày —
            // không phải cây WBS, chỉ là danh sách phẳng có start_time/due_time.
            'Tuần cao điểm phối hợp liên phòng ban' => $this->overlapDemoTasks(),

            'Nghiên cứu ứng dụng AI hỗ trợ giảng dạy' => [
                [
                    'title' => 'Khảo sát công cụ AI hiện có',
                    'status' => 'cancelled',
                    'priority' => 'low',
                    'assignee_email' => 'duc.cntt@example.com',
                    'start_date' => now()->subDays(60)->toDateString(),
                    'end_date' => now()->subDays(45)->toDateString(),
                    'actual_start_date' => now()->subDays(60)->toDateString(),
                    'progress_percent' => 20,
                    'description' => 'Huỷ vì trùng phạm vi với dự án đào tạo trực tuyến.',
                ],
                [
                    'title' => 'PoC chatbot hỏi đáp bài giảng',
                    'status' => 'cancelled',
                    'priority' => 'medium',
                    'assignee_email' => ':creator',
                    'start_date' => now()->subDays(50)->toDateString(),
                    'end_date' => now()->subDays(30)->toDateString(),
                    'progress_percent' => 5,
                ],
            ],
        ];
    }

    /**
     * Danh sách phẳng công việc có giờ cụ thể (start_time/due_time), cố tình
     * chồng khung giờ + trải nhiều người thực hiện (nhiều phòng ban: CNTT,
     * Đào tạo, Nhân sự, Tài chính, BGH) trong cùng 1 ngày — dữ liệu để kiểm
     * tra chế độ xem theo tuần của Lịch công việc scale tốt khi có nhiều
     * việc chồng giờ (TaskCalendarView.vue::layoutTimedColumns()).
     *
     * @return list<array<string, mixed>>
     */
    private function overlapDemoTasks(): array
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        return [
            // Hôm nay 08:00–09:30 — 4 việc chồng nhau, 4 phòng ban khác nhau.
            [
                'title' => 'Họp giao ban đầu tuần',
                'status' => 'in_progress',
                'priority' => 'high',
                'assignee_email' => ':creator',
                'start_date' => $today,
                'end_date' => $today,
                'start_time' => '08:00',
                'due_time' => '09:00',
                'progress_percent' => 30,
                'description' => 'Điểm nhanh tiến độ các phòng ban trong tuần.',
            ],
            [
                'title' => 'Chuẩn bị phòng máy thi thử',
                'status' => 'not_started',
                'priority' => 'medium',
                'assignee_email' => 'duc.cntt@example.com',
                'start_date' => $today,
                'end_date' => $today,
                'start_time' => '08:15',
                'due_time' => '09:30',
                'progress_percent' => 0,
            ],
            [
                'title' => 'Tư vấn tuyển sinh trực tiếp',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'assignee_email' => 'tuan.dt@example.com',
                'start_date' => $today,
                'end_date' => $today,
                'start_time' => '08:30',
                'due_time' => '09:15',
                'progress_percent' => 60,
            ],
            [
                'title' => 'Đối chiếu bảng lương tháng',
                'status' => 'on_hold',
                'priority' => 'medium',
                'assignee_email' => 'phong.tc@example.com',
                'start_date' => $today,
                'end_date' => $today,
                'start_time' => '08:45',
                'due_time' => '09:45',
                'progress_percent' => 20,
                'description' => 'Tạm dừng chờ số liệu từ phòng Nhân sự.',
            ],

            // Hôm nay 10:00–11:30 — 2 việc chồng 1 phần (không phải toàn bộ).
            [
                'title' => 'Phỏng vấn ứng viên giảng viên',
                'status' => 'in_progress',
                'priority' => 'high',
                'assignee_email' => 'son.ns@example.com',
                'start_date' => $today,
                'end_date' => $today,
                'start_time' => '10:00',
                'due_time' => '11:00',
                'progress_percent' => 45,
            ],
            [
                'title' => 'Bàn giao thiết bị phòng Lab 2',
                'status' => 'completed',
                'priority' => 'low',
                'assignee_email' => 'linh.cntt@example.com',
                'start_date' => $today,
                'end_date' => $today,
                'start_time' => '10:30',
                'due_time' => '11:30',
                'progress_percent' => 100,
            ],

            // Hôm nay 14:00 — 1 việc riêng lẻ, không chồng.
            [
                'title' => 'Duyệt nội dung truyền thông tuyển sinh',
                'status' => 'not_started',
                'priority' => 'medium',
                'assignee_email' => 'mai.dt@example.com',
                'start_date' => $today,
                'end_date' => $today,
                'start_time' => '14:00',
                'due_time' => '15:00',
                'progress_percent' => 0,
            ],

            // Hôm nay, cả ngày (không giờ) — kiểm tra dải "Cả ngày".
            [
                'title' => 'Trực đường dây nóng tuyển sinh',
                'status' => 'in_progress',
                'priority' => 'low',
                'assignee_email' => 'hoa.bgh@example.com',
                'start_date' => $today,
                'end_date' => $tomorrow,
                'progress_percent' => 50,
            ],

            // Ngày mai 08:30–10:00 — 3 việc chồng nhau, để kiểm tra cụm khác
            // cùng lúc hiển thị đúng cột riêng cho từng ngày.
            [
                'title' => 'Kiểm tra hệ thống mạng trước sự kiện',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'assignee_email' => 'duc.cntt@example.com',
                'start_date' => $tomorrow,
                'end_date' => $tomorrow,
                'start_time' => '08:30',
                'due_time' => '10:00',
                'progress_percent' => 35,
            ],
            [
                'title' => 'Tập huấn lễ tân đón phụ huynh',
                'status' => 'not_started',
                'priority' => 'medium',
                'assignee_email' => 'thao.ns@example.com',
                'start_date' => $tomorrow,
                'end_date' => $tomorrow,
                'start_time' => '09:00',
                'due_time' => '10:00',
                'progress_percent' => 0,
            ],
            [
                'title' => 'Duyệt kịch bản chương trình khai mạc',
                'status' => 'not_started',
                'priority' => 'high',
                'assignee_email' => 'yen.tc@example.com',
                'start_date' => $tomorrow,
                'end_date' => $tomorrow,
                'start_time' => '09:15',
                'due_time' => '09:45',
                'progress_percent' => 0,
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, User>  $users
     * @param  list<array<string, mixed>>  $nodes
     */
    private function seedNodes(
        TaskService $service,
        Project $project,
        User $creator,
        $users,
        array $nodes,
        ?int $parentId,
    ): void {
        foreach (array_values($nodes) as $index => $node) {
            $assigneeId = $this->resolveAssignee($node['assignee_email'] ?? null, $users, $creator);

            $result = $service->create($project, [
                'parent_id' => $parentId,
                'type' => $node['type'] ?? 'task',
                'title' => $node['title'],
                'description' => $node['description'] ?? null,
                'status' => $node['status'] ?? 'not_started',
                'priority' => $node['priority'] ?? null,
                'start_date' => $node['start_date'] ?? null,
                'start_time' => $node['start_time'] ?? null,
                'end_date' => $node['end_date'] ?? null,
                'due_time' => $node['due_time'] ?? null,
                'actual_start_date' => $node['actual_start_date'] ?? null,
                'actual_end_date' => $node['actual_end_date'] ?? null,
                'assignee_id' => $assigneeId,
                'progress_percent' => $node['progress_percent'] ?? null,
                'sort_order' => $index,
                'code' => sprintf('CV%04d', $this->codeSeq++),
            ], $creator);

            if (is_array($result) || ($node['children'] ?? []) === []) {
                continue;
            }

            $this->seedNodes($service, $project, $creator, $users, $node['children'], $result->id);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, User>  $users
     */
    private function resolveAssignee(?string $email, $users, User $creator): ?int
    {
        if ($email === null || $email === '') {
            return null;
        }

        if ($email === ':creator') {
            return $creator->id;
        }

        return $users->get($email)?->id;
    }
}
