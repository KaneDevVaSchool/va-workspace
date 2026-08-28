<?php

/**
 * PermissionCatalog — MA TRẬN PHÂN QUYỀN THEO ROLE (config-based defaults)
 *
 * Nguồn sự thật: docs/VA_WORKSPACE_OVERVIEW.md §4.2–§4.3
 *
 * HIERARCHY (PermissionService::allows):
 *   '*'          → tất cả permission (super_admin)
 *   'module.*'   → tất cả action trong module
 *   'module.action' → action cụ thể
 *
 * SCOPE:
 *   global     → không giới hạn scope tổ chức
 *   department → user phải thuộc đúng phòng ban (scope_id = department_id)
 *   team       → user phải thuộc đúng team (scope_id = team_id)
 *
 * OVERRIDE: super_admin có thể thêm/thu hồi quyền qua bảng permission_grants
 * (xem Modules/Identity/Database/migrations/*_create_permission_grants_table.php).
 *
 * RESERVED KEYS (không được grant qua UI cho role khác):
 *   xem 'reserved' bên dưới — chỉ super_admin giữ được.
 *   Ngoại lệ: 'initiative.assign_department' được cấp cho director_officer trở lên.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Ma trận mặc định: role_code => permission_keys[]
    |--------------------------------------------------------------------------
    | Dùng '*' để cấp toàn quyền, 'module.*' để cấp toàn module.
    | Danh sách keys mở rộng theo từng Phase — thêm key mới vào đây khi có
    | module mới, không cần sửa PermissionService.
    */
    'matrix' => [

        'super_admin' => [
            '*',
        ],

        'admin' => [
            // Nghiệp vụ cốt lõi — quản lý toàn bộ trừ cấu hình hệ thống
            'initiative.*',
            'project.*',
            'task.*',
            'daily_report.*',
            'kpi.*',
            'dashboard.*',
            'department.*',
            'team.*',
            'evaluation.*',
            'contract.*',
            'credential.*',
            'knowledge_base.*',
            'ai_account.*',
            'weekly_report.*',
            'blocker.*',
            'test_case.*',
            'feedback.*',
            'worklog.*',
            'performance.*',
            'process.*',
            'material_planning.*',
            'project_finance.*',
            'document_manager.*',
            'my_work.*',
            'notification.*',
            'social.*',
        ],

        'director_officer' => [
            // Hạng mục liên phòng ban
            'initiative.create',
            'initiative.view',
            'initiative.update_own',
            'initiative.assign_department',
            'initiative.track_progress',
            // Xem dự án (read-only — không tạo/sửa task nội bộ PB)
            'project.view',
            'task.view',
            'task.delegate',
            // Báo cáo ngày của bản thân
            'daily_report.write',
            // KPI theo phòng ban liên quan
            'kpi.view',
            'kpi.view_department',
            'kpi.view_own',
            // Tổng quan
            'dashboard.view',
            'performance.view_department',
            'weekly_report.view',
            'my_work.*',
            'notification.*',
        ],

        'department_director' => [
            // Dự án & công việc trong phòng ban
            'project.create',
            'project.view',
            'project.manage_department',
            'project.update_department',
            'task.create',
            'task.view',
            'task.assign',
            'task.delegate',
            'task.approve',
            // Báo cáo ngày
            'daily_report.write',
            'daily_report.approve',
            'daily_report.score',
            // KPI phòng ban
            'kpi.view_department',
            'kpi.view_own',
            // Hạng mục (nhận hạng mục từ director_officer)
            'initiative.view',
            'initiative.track_progress',
            // Quản lý nhóm trong phòng ban
            'team.view',
            'team.manage',
            // Hợp đồng, KB, Đánh giá, Blocker, TestCase trong PB
            'contract.manage_department',
            'knowledge_base.manage_department',
            'evaluation.manage_department',
            'evaluation.manage_global_template',
            'blocker.manage',
            'test_case.manage',
            'feedback.view',
            // Quy trình, Vật tư, Tài chính dự án
            'process.manage_department',
            'material_planning.manage_department',
            'project_finance.view',
            'project_finance.manage_department',
            // Workload, Báo cáo hiệu suất
            'worklog.view',
            'performance.view_department',
            // Hub cấu hình Workspace của phòng ban (thành viên, tiêu chí
            // đánh giá tự tạo, bật/tắt menu sidebar) — xem Modules/WorkspaceConfig
            'workspace_config.view_department',
            'workspace_config.manage_sidebar_department',
            'workspace_config.assign_role_department',
            // Dashboard, My Work
            'dashboard.view',
            'weekly_report.view',
            'my_work.*',
            'notification.*',
            // Bảng tin nội bộ — kiểm duyệt bài/bình luận và ghim thông báo
            // trong phòng ban mình
            'social.moderate',
            'social.pin',
        ],

        'deputy_department_director' => [
            // Gần trưởng phòng — cao hơn trưởng bộ phận
            'project.create',
            'project.view',
            'project.manage_department',
            'project.update_department',
            'task.create',
            'task.view',
            'task.assign',
            'task.delegate',
            'task.approve',
            'daily_report.write',
            'daily_report.approve',
            'daily_report.score',
            'kpi.view_department',
            'kpi.view_own',
            'initiative.view',
            'initiative.track_progress',
            'team.view',
            'team.manage',
            'contract.manage_department',
            'knowledge_base.manage_department',
            'evaluation.manage_department',
            'evaluation.manage_global_template',
            'blocker.manage',
            'test_case.manage',
            'feedback.view',
            'process.manage_department',
            'material_planning.manage_department',
            'project_finance.view',
            'project_finance.manage_department',
            'worklog.view',
            'performance.view_department',
            'workspace_config.view_department',
            'workspace_config.manage_sidebar_department',
            'workspace_config.assign_role_department',
            'dashboard.view',
            'weekly_report.view',
            'my_work.*',
            'notification.*',
            'social.moderate',
            'social.pin',
        ],

        'section_head' => [
            // Trưởng bộ phận — dưới phó phòng, trên trưởng nhóm
            'project.view',
            'project.create',
            'task.create',
            'task.view',
            'task.assign',
            'task.delegate',
            'daily_report.write',
            'daily_report.approve',
            'kpi.view_team',
            'kpi.view_department',
            'kpi.view_own',
            'initiative.view',
            'blocker.manage',
            'test_case.manage',
            'worklog.view',
            'performance.view_department',
            'dashboard.view',
            'weekly_report.view',
            'my_work.*',
            'notification.*',
            'social.moderate',
            'social.pin',
        ],

        'team_lead' => [
            // Dự án & công việc trong nhóm
            'project.view',
            'task.create',
            'task.view',
            'task.assign',
            'task.delegate',
            // Báo cáo ngày
            'daily_report.write',
            'daily_report.approve_team',
            // KPI nhóm & cá nhân
            'kpi.view_team',
            'kpi.view_own',
            // Xem hạng mục (read-only nếu nhóm có task gắn initiative)
            'initiative.view',
            // Blocker, TestCase trong phạm vi nhóm
            'blocker.manage',
            'test_case.manage',
            // Worklog
            'worklog.view',
            // Dashboard, My Work
            'dashboard.view',
            'my_work.*',
            'notification.*',
            'social.moderate',
            'social.pin',
        ],

        'member' => [
            // Công việc được giao
            'task.view_assigned',
            // Báo cáo ngày của bản thân
            'daily_report.write',
            // KPI cá nhân
            'kpi.view_own',
            // Tổng quan (read-only cơ bản)
            'dashboard.view',
            // Đề xuất, TestCase, KB, Worklog
            'feedback.create',
            'test_case.create',
            'knowledge_base.create',
            'worklog.write',
            'blocker.create',
            // My Work
            'my_work.*',
            'notification.*',
        ],

        'viewer' => [
            'dashboard.view',
            'project.view',
            'kpi.view_own',
            'performance.view',
            'contract.view',
            'notification.*',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved keys — chỉ super_admin, không thể grant qua UI cho role khác
    |--------------------------------------------------------------------------
    | PermissionService::isReserved($key) trả true nếu key match bất kỳ pattern
    | nào dưới đây. UI /superadmin/permissions phải ẩn/block các keys này.
    |
    | Ngoại lệ được quản lý trong 'reserved_exceptions' bên dưới.
    */
    'reserved' => [
        'system.settings.*',
        'permissions.manage',
        'roles.assign',
        'workspace.hub.manage',
        'workspace.evaluation.*',
        'workspace.daily_report_scoring.*',
        'workspace.task_scoring.*',
        'workspace_config.view_all',
        'workspace_config.manage_global_menu',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ngoại lệ: reserved key nhưng được cấp cho role cụ thể theo spec
    |--------------------------------------------------------------------------
    | Format: [ 'permission_key' => ['role_code', ...] ]
    */
    'reserved_exceptions' => [
        'initiative.assign_department' => ['director_officer', 'admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog — nhãn tiếng Việt + module cho UI /superadmin/permissions
    |--------------------------------------------------------------------------
    | Mọi key xuất hiện trong 'matrix' (trừ '*') phải có mặt ở đây, kể cả
    | key wildcard 'module.*' (đại diện "Toàn bộ module X"). PermissionMatrixController
    | build danh sách permissions cho UI hoàn toàn từ đây — frontend không tự
    | suy luận label.
    |
    | 'active' => true: chức năng đã có trong app (trang/API đang dùng). Ma trận
    | UI chỉ hiện các key này; key còn lại giữ trong catalog để Phase sau bật
    | khi module tương ứng được dựng, không lộ quyền "treo" cho super_admin.
    */
    'catalog' => [

        // ---------- Hạng mục (initiative) ----------
        'initiative.*' => ['label' => 'Toàn bộ hạng mục', 'module' => 'Hạng mục', 'description' => 'Toàn quyền quản lý hạng mục liên phòng ban'],
        'initiative.create' => ['label' => 'Tạo hạng mục', 'module' => 'Hạng mục', 'description' => 'Tạo mới hạng mục liên phòng ban'],
        'initiative.view' => ['label' => 'Xem hạng mục', 'module' => 'Hạng mục', 'description' => 'Xem danh sách và chi tiết hạng mục'],
        'initiative.update_own' => ['label' => 'Sửa hạng mục của mình', 'module' => 'Hạng mục', 'description' => 'Sửa hạng mục do chính mình tạo'],
        'initiative.assign_department' => ['label' => 'Giao hạng mục cho phòng ban', 'module' => 'Hạng mục', 'description' => 'Phân công hạng mục cho phòng ban thực hiện'],
        'initiative.track_progress' => ['label' => 'Theo dõi tiến độ hạng mục', 'module' => 'Hạng mục', 'description' => 'Xem/cập nhật tiến độ thực hiện hạng mục'],

        // ---------- Dự án (project) ----------
        'project.*' => ['label' => 'Toàn bộ dự án', 'module' => 'Dự án', 'description' => 'Toàn quyền quản lý dự án'],
        'project.view' => ['label' => 'Xem dự án', 'module' => 'Dự án', 'description' => 'Xem danh sách và chi tiết dự án'],
        'project.create' => ['label' => 'Tạo dự án', 'module' => 'Dự án', 'description' => 'Tạo mới dự án'],
        'project.manage_department' => ['label' => 'Quản lý dự án phòng ban', 'module' => 'Dự án', 'description' => 'Quản lý toàn bộ dự án trong phòng ban'],
        'project.update_department' => ['label' => 'Sửa dự án phòng ban', 'module' => 'Dự án', 'description' => 'Cập nhật thông tin dự án thuộc phòng ban'],
        'project.manage_settings' => ['label' => 'Cấu hình dự án toàn hệ thống', 'module' => 'Dự án', 'description' => 'Cấu hình mã dự án, quy tắc hoạt động và danh sách nhân sự được phép tạo dự án (chỉ admin/super_admin — đã bao trùm qua project.*)', 'active' => true],

        // ---------- Công việc (task) ----------
        'task.*' => ['label' => 'Toàn bộ công việc', 'module' => 'Công việc', 'description' => 'Toàn quyền quản lý công việc/task', 'active' => true],
        'task.view' => ['label' => 'Xem công việc', 'module' => 'Công việc', 'description' => 'Xem danh sách và chi tiết công việc', 'active' => true],
        'task.view_assigned' => ['label' => 'Xem công việc được giao', 'module' => 'Công việc', 'description' => 'Chỉ xem công việc được giao cho bản thân', 'active' => true],
        'task.delegate' => ['label' => 'Uỷ quyền công việc', 'module' => 'Công việc', 'description' => 'Cho phép giao/chuyển task cho người khác thực hiện', 'active' => true],
        'task.create' => ['label' => 'Tạo công việc', 'module' => 'Công việc', 'description' => 'Tạo mới công việc/task', 'active' => true],
        'task.assign' => ['label' => 'Phân công công việc', 'module' => 'Công việc', 'description' => 'Giao task cho thành viên trong nhóm/phòng ban', 'active' => true],
        'task.approve' => ['label' => 'Duyệt công việc', 'module' => 'Công việc', 'description' => 'Phê duyệt hoàn thành công việc', 'active' => true],

        // ---------- Báo cáo ngày (daily_report) ----------
        'daily_report.*' => ['label' => 'Toàn bộ báo cáo ngày', 'module' => 'Báo cáo ngày', 'description' => 'Toàn quyền quản lý báo cáo ngày'],
        'daily_report.write' => ['label' => 'Viết báo cáo ngày', 'module' => 'Báo cáo ngày', 'description' => 'Viết báo cáo công việc hằng ngày của bản thân'],
        'daily_report.approve' => ['label' => 'Duyệt báo cáo ngày', 'module' => 'Báo cáo ngày', 'description' => 'Phê duyệt báo cáo ngày của nhân sự phòng ban'],
        'daily_report.approve_team' => ['label' => 'Duyệt báo cáo ngày trong nhóm', 'module' => 'Báo cáo ngày', 'description' => 'Phê duyệt báo cáo ngày của thành viên trong nhóm'],
        'daily_report.score' => ['label' => 'Chấm điểm báo cáo ngày', 'module' => 'Báo cáo ngày', 'description' => 'Chấm điểm chất lượng báo cáo ngày'],

        // ---------- KPI ----------
        'kpi.*' => ['label' => 'Toàn bộ KPI', 'module' => 'KPI', 'description' => 'Toàn quyền quản lý KPI'],
        'kpi.view' => ['label' => 'Xem KPI', 'module' => 'KPI', 'description' => 'Xem KPI tổng quan'],
        'kpi.view_department' => ['label' => 'Xem KPI phòng ban', 'module' => 'KPI', 'description' => 'Xem KPI của toàn phòng ban'],
        'kpi.view_team' => ['label' => 'Xem KPI nhóm', 'module' => 'KPI', 'description' => 'Xem KPI của nhóm mình phụ trách'],
        'kpi.view_own' => ['label' => 'Xem KPI cá nhân', 'module' => 'KPI', 'description' => 'Xem KPI của chính mình'],

        // ---------- Dashboard ----------
        'dashboard.*' => ['label' => 'Toàn bộ Dashboard', 'module' => 'Dashboard', 'description' => 'Toàn quyền xem/tuỳ biến dashboard'],
        'dashboard.view' => ['label' => 'Xem tổng quan', 'module' => 'Tổng quan', 'description' => 'Xem trang tổng quan sau khi đăng nhập', 'active' => true],

        // ---------- Phòng ban (department) ----------
        'department.*' => ['label' => 'Toàn bộ phòng ban', 'module' => 'Phòng ban', 'description' => 'Toàn quyền quản lý phòng ban', 'active' => true],

        // ---------- Nhóm (team) ----------
        'team.*' => ['label' => 'Toàn bộ nhóm', 'module' => 'Nhóm', 'description' => 'Toàn quyền quản lý nhóm'],
        'team.view' => ['label' => 'Xem nhóm', 'module' => 'Nhóm', 'description' => 'Xem danh sách nhóm trong phòng ban', 'active' => true],
        'team.manage' => ['label' => 'Quản lý nhóm', 'module' => 'Nhóm', 'description' => 'Tạo/sửa/xoá nhóm, gán trưởng nhóm', 'active' => true],

        // ---------- Đánh giá (evaluation) ----------
        'evaluation.*' => ['label' => 'Toàn bộ đánh giá', 'module' => 'Đánh giá', 'description' => 'Toàn quyền quản lý đánh giá nhân sự'],
        'evaluation.manage_department' => ['label' => 'Quản lý đánh giá phòng ban', 'module' => 'Đánh giá', 'description' => 'Tự tạo và quản lý tiêu chí đánh giá nhân sự trong phòng ban mình', 'active' => true],
        'evaluation.manage_global_template' => ['label' => 'Quản lý mẫu đánh giá dùng chung', 'module' => 'Đánh giá', 'description' => 'Đánh dấu mẫu đánh giá dùng chung cho toàn bộ phòng ban', 'active' => true],

        // ---------- Hợp đồng (contract) ----------
        'contract.*' => ['label' => 'Toàn bộ hợp đồng', 'module' => 'Hợp đồng', 'description' => 'Toàn quyền quản lý hợp đồng'],
        'contract.view' => ['label' => 'Xem hợp đồng', 'module' => 'Hợp đồng', 'description' => 'Xem danh sách và chi tiết hợp đồng'],
        'contract.manage_department' => ['label' => 'Quản lý hợp đồng phòng ban', 'module' => 'Hợp đồng', 'description' => 'Quản lý hợp đồng thuộc phòng ban'],

        // ---------- Thông tin đăng nhập (credential) ----------
        'credential.*' => ['label' => 'Toàn bộ thông tin đăng nhập', 'module' => 'Thông tin đăng nhập', 'description' => 'Toàn quyền quản lý credential hệ thống/dịch vụ'],

        // ---------- Kho tri thức (knowledge_base) ----------
        'knowledge_base.*' => ['label' => 'Toàn bộ kho tri thức', 'module' => 'Kho tri thức', 'description' => 'Toàn quyền quản lý kho tri thức'],
        'knowledge_base.create' => ['label' => 'Tạo bài viết tri thức', 'module' => 'Kho tri thức', 'description' => 'Tạo mới bài viết trong kho tri thức'],
        'knowledge_base.manage_department' => ['label' => 'Quản lý kho tri thức phòng ban', 'module' => 'Kho tri thức', 'description' => 'Quản lý kho tri thức của phòng ban'],

        // ---------- Tài khoản AI (ai_account) ----------
        'ai_account.*' => ['label' => 'Toàn bộ tài khoản AI', 'module' => 'Tài khoản AI', 'description' => 'Toàn quyền quản lý tài khoản dịch vụ AI'],

        // ---------- Báo cáo tuần (weekly_report) ----------
        'weekly_report.*' => ['label' => 'Toàn bộ báo cáo tuần', 'module' => 'Báo cáo tuần', 'description' => 'Toàn quyền quản lý báo cáo tuần'],
        'weekly_report.view' => ['label' => 'Xem báo cáo tuần', 'module' => 'Báo cáo tuần', 'description' => 'Xem báo cáo tổng hợp theo tuần'],

        // ---------- Vướng mắc (blocker) ----------
        'blocker.*' => ['label' => 'Toàn bộ vướng mắc', 'module' => 'Vướng mắc', 'description' => 'Toàn quyền quản lý vướng mắc/blocker'],
        'blocker.create' => ['label' => 'Tạo vướng mắc', 'module' => 'Vướng mắc', 'description' => 'Tạo mới báo cáo vướng mắc'],
        'blocker.manage' => ['label' => 'Quản lý vướng mắc', 'module' => 'Vướng mắc', 'description' => 'Xử lý, phân công, đóng vướng mắc'],

        // ---------- Test case ----------
        'test_case.*' => ['label' => 'Toàn bộ test case', 'module' => 'Test case', 'description' => 'Toàn quyền quản lý test case'],
        'test_case.create' => ['label' => 'Tạo test case', 'module' => 'Test case', 'description' => 'Tạo mới test case'],
        'test_case.manage' => ['label' => 'Quản lý test case', 'module' => 'Test case', 'description' => 'Sửa/xoá/thực thi test case'],

        // ---------- Đề xuất/phản hồi (feedback) ----------
        'feedback.*' => ['label' => 'Toàn bộ đề xuất', 'module' => 'Đề xuất', 'description' => 'Toàn quyền quản lý đề xuất/phản hồi'],
        'feedback.create' => ['label' => 'Tạo đề xuất', 'module' => 'Đề xuất', 'description' => 'Gửi đề xuất/phản hồi mới'],
        'feedback.view' => ['label' => 'Xem đề xuất', 'module' => 'Đề xuất', 'description' => 'Xem danh sách đề xuất/phản hồi'],

        // ---------- Nhật ký công việc (worklog) ----------
        'worklog.*' => ['label' => 'Toàn bộ worklog', 'module' => 'Worklog', 'description' => 'Toàn quyền quản lý nhật ký công việc'],
        'worklog.view' => ['label' => 'Xem worklog', 'module' => 'Worklog', 'description' => 'Xem nhật ký công việc của nhân sự'],
        'worklog.write' => ['label' => 'Ghi worklog', 'module' => 'Worklog', 'description' => 'Ghi nhật ký công việc của bản thân'],

        // ---------- Hiệu suất (performance) ----------
        'performance.*' => ['label' => 'Toàn bộ hiệu suất', 'module' => 'Hiệu suất', 'description' => 'Toàn quyền xem/quản lý báo cáo hiệu suất'],
        'performance.view' => ['label' => 'Xem hiệu suất', 'module' => 'Hiệu suất', 'description' => 'Xem báo cáo hiệu suất'],
        'performance.view_department' => ['label' => 'Xem hiệu suất phòng ban', 'module' => 'Hiệu suất', 'description' => 'Xem báo cáo hiệu suất của phòng ban'],

        // ---------- Quy trình (process) ----------
        'process.*' => ['label' => 'Toàn bộ quy trình', 'module' => 'Quy trình', 'description' => 'Toàn quyền quản lý quy trình'],
        'process.manage_department' => ['label' => 'Quản lý quy trình phòng ban', 'module' => 'Quy trình', 'description' => 'Quản lý quy trình áp dụng trong phòng ban'],

        // ---------- Kế hoạch vật tư (material_planning) ----------
        'material_planning.*' => ['label' => 'Toàn bộ kế hoạch vật tư', 'module' => 'Kế hoạch vật tư', 'description' => 'Toàn quyền quản lý kế hoạch vật tư'],
        'material_planning.manage_department' => ['label' => 'Quản lý vật tư phòng ban', 'module' => 'Kế hoạch vật tư', 'description' => 'Quản lý kế hoạch vật tư của phòng ban'],

        // ---------- Tài chính dự án (project_finance) ----------
        'project_finance.*' => ['label' => 'Toàn bộ tài chính dự án', 'module' => 'Tài chính dự án', 'description' => 'Toàn quyền quản lý tài chính dự án'],
        'project_finance.view' => ['label' => 'Xem tài chính dự án', 'module' => 'Tài chính dự án', 'description' => 'Xem thông tin tài chính dự án'],
        'project_finance.manage_department' => ['label' => 'Quản lý tài chính dự án phòng ban', 'module' => 'Tài chính dự án', 'description' => 'Quản lý tài chính dự án thuộc phòng ban'],

        // ---------- Quản lý tài liệu (document_manager) ----------
        'document_manager.*' => ['label' => 'Toàn bộ quản lý tài liệu', 'module' => 'Quản lý tài liệu', 'description' => 'Toàn quyền quản lý tài liệu'],

        // ---------- Việc của tôi (my_work) ----------
        'my_work.*' => ['label' => 'Toàn bộ Việc của tôi', 'module' => 'Việc của tôi', 'description' => 'Toàn quyền khu vực tổng hợp công việc cá nhân'],

        // ---------- Thông báo (notification) ----------
        'notification.*' => ['label' => 'Toàn bộ thông báo', 'module' => 'Thông báo', 'description' => 'Toàn quyền quản lý thông báo'],

        // ---------- Reserved: Hệ thống (system) ----------
        'system.settings.*' => ['label' => 'Cấu hình hệ thống', 'module' => 'Hệ thống', 'description' => 'Toàn quyền cấu hình hệ thống (chỉ super_admin)'],
        'permissions.manage' => ['label' => 'Quản lý phân quyền', 'module' => 'Hệ thống', 'description' => 'Cấp/thu hồi quyền của các role (chỉ super_admin)', 'active' => true],
        'roles.assign' => ['label' => 'Gán vai trò', 'module' => 'Hệ thống', 'description' => 'Gán/gỡ vai trò hệ thống cho user (chỉ super_admin)'],
        'workspace.hub.manage' => ['label' => 'Quản lý Hub Workspace', 'module' => 'Hệ thống', 'description' => 'Quản lý cấu hình trung tâm của Workspace (chỉ super_admin)'],
        'workspace.evaluation.*' => ['label' => 'Toàn bộ cấu hình đánh giá hệ thống', 'module' => 'Hệ thống', 'description' => 'Tạo tiêu chí đánh giá chung dùng toàn hệ thống (chỉ super_admin)', 'active' => true],
        'workspace.daily_report_scoring.*' => ['label' => 'Cấu hình chấm điểm báo cáo ngày', 'module' => 'Hệ thống', 'description' => 'Cấu hình quy tắc chấm điểm báo cáo ngày toàn hệ thống (chỉ super_admin)'],
        'workspace.task_scoring.*' => ['label' => 'Cấu hình chấm điểm công việc', 'module' => 'Hệ thống', 'description' => 'Quản lý danh mục tiêu chí chấm điểm công việc dùng chung toàn hệ thống (chỉ super_admin)'],
        'workspace_config.view_all' => ['label' => 'Xem toàn bộ cấu hình workspace', 'module' => 'Hệ thống', 'description' => 'Xem tổng hợp cấu hình workspace mọi phòng ban (chỉ super_admin)', 'active' => true],
        'workspace_config.manage_global_menu' => ['label' => 'Ẩn/hiện menu toàn hệ thống', 'module' => 'Hệ thống', 'description' => 'Ẩn/hiện bất kỳ mục menu sidebar nào cho toàn bộ tài khoản không phải super_admin (chỉ super_admin)', 'active' => true],

        // ---------- Cấu hình Workspace theo phòng ban (workspace_config) ----------
        'workspace_config.view_department' => ['label' => 'Xem cấu hình phòng ban', 'module' => 'Cấu hình phòng ban', 'description' => 'Xem trang cấu hình workspace của phòng ban mình (thành viên, menu)', 'active' => true],
        'workspace_config.manage_sidebar_department' => ['label' => 'Cấu hình menu phòng ban', 'module' => 'Cấu hình phòng ban', 'description' => 'Bật/tắt mục menu hiển thị cho phòng ban mình', 'active' => true],
        'workspace_config.assign_role_department' => ['label' => 'Gán vai trò phòng ban', 'module' => 'Cấu hình phòng ban', 'description' => 'Gán vai trò (phó phòng, trưởng bộ phận, trưởng nhóm, nhân viên, người xem) cho thành viên phòng ban mình', 'active' => true],

        // ---------- Mạng nội bộ (social) ----------
        'social.*' => ['label' => 'Toàn bộ bảng tin', 'module' => 'Mạng nội bộ', 'description' => 'Toàn quyền quản lý bảng tin nội bộ'],
        'social.moderate' => ['label' => 'Kiểm duyệt bảng tin', 'module' => 'Mạng nội bộ', 'description' => 'Xoá bài viết/bình luận của người khác trong phòng ban quản lý', 'active' => true],
        'social.pin' => ['label' => 'Ghim thông báo', 'module' => 'Mạng nội bộ', 'description' => 'Đưa bài viết lên bảng Thông báo công ty. Thông báo quan trọng do người quản trị đăng riêng.', 'active' => true],
        'social.review' => ['label' => 'Duyệt bài viết', 'module' => 'Mạng nội bộ', 'description' => 'Duyệt hoặc từ chối bài viết mới trước khi hiển thị công khai trên bảng tin, áp dụng toàn trường', 'active' => true],

    ],

];
