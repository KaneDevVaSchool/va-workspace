# Đối soát 14/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W38](../summary/2026-W38-summary.md) · Bảng tuần: [2026-W38-audit](../summary/2026-W38-audit.md)

Việc trong ngày đánh số version **1.1**, **1.2**, … Mỗi việc một bảng Đổi gì / Vì sao / Tác dụng. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Seed nhân sự CNTT, xuất Excel Credential, quản lý task nhanh, cấu hình menu sidebar

Tổng quan · Quyền và nhật ký, Công việc và dự án, Cấu hình đơn từ, Hồ sơ đăng nhập, Tổng quan của tôi, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Seed nhân sự CNTT, xuất Excel Credential, quản lý task nhanh, cấu hình menu sidebar. - Modules/Identity: seed nhân sự thật phòng CNTT (ban lãnh đạo + team Phần   mềm/Phần cứng) qua CnttSoftwareTeamSeeder, thay cho HrNsSuperAdminDepartmentSeeder   đã xoá; BulkUpsertPermissionGrantRequest + cập nhật Permission Matrix UI. - Modules/Credential: export Excel, đồng bộ viewer, tài khoản ngoài/nội bộ,   migration bổ sung cột owner/access_url/group/department_id. - Modules/Project: quick-add task, export báo cáo/PDF, cập nhật tab Thảo   luận/Feedback/Testcase/Report. - Modules/WorkspaceConfig: cấu hình hiển thị menu sidebar theo phòng ban.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 14:54 | `1d1d158` | Thêm | Credential/CredentialEnums.php, Credential/CredentialController.php, Credential/CredentialViewerController.php, Credential/StoreCredentialRequest.php (+108) |

### 1.2. Drop đúng tên FK tường minh khi rollback 3 migration

Dự án và công việc · Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Drop đúng tên FK tường minh khi rollback 3 migration. dropConstrainedForeignId() tự suy tên constraint theo convention mặc định ({table}_{column}_foreign), nhưng 3 migration này đặt tên FK tường minh qua constrained($table, 'id', $customName) ở up() — down() gọi dropConstrainedForeignId() bị lỗi "Can't DROP FOREIGN KEY ... check that it exists" vì tìm sai tên. Sửa cả 3 dùng dropForeign($customName) + dropColumn() riêng, theo đúng pattern đã có sẵn ở 2026_08_30_100003_add_people_fields_to_tasks_table.php.  Phát hiện khi chạy `php artisan migrate:refresh` trên server production — lệnh dừng giữa chừng ở add_folder_id_to_project_attachments_table.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì tìm sai tên |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi bảng công việc |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 15:05 | `44ea7ae` | Sửa | Project/2026_08_27_100005_add_departments_to_projects_table.php, Project/2026_08_28_100001_upgrade_project_create_fields.php, Project/2026_09_09_100002_add_folder_id_to_project_attachments_table.php |
| 15:30 | `225b03b` | Sửa tiếp | Credential/CredentialProviderService.php, Credential/CredentialService.php, Evaluation/EvaluationCriteriaController.php, Identity/ShortcutController.php (+22) |

### 1.3. Sửa tiếp phần Đánh giá (chưa ghi câu mô tả)

Đánh giá · Không gắn màn hình · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc không gắn màn hình, đụng Evaluation/2026_08_26_150001_add_unique_name_per_department_to_evaluation_criteria_table.php, Evaluation/2026_08_26_120001_create_evaluation_positions_table.php. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 15:32 | `704e310` | Sửa tiếp | Evaluation/2026_08_26_150001_add_unique_name_per_department_to_evaluation_criteria_table.php |
| 15:34 | `c0a4bf4` | Sửa tiếp | Evaluation/2026_08_26_120001_create_evaluation_positions_table.php |

### 1.4. Drop bảng pivot mồ côi trước khi rollback evaluation_criteria/positions

Đánh giá · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Drop bảng pivot mồ côi trước khi rollback evaluation_criteria/positions. down() của 2026_08_31_000002_drop_evaluation_templates_tables dựng lại evaluation_template_criteria/evaluation_template_positions (có FK trỏ tới evaluation_criteria/evaluation_positions) để rollback đối xứng khi lùi 1 bước. Nhưng khi migrate:refresh tiếp tục lùi qua các migration cũ hơn, down() của evaluation_criteria/evaluation_positions gặp lỗi 3730 "Cannot drop table ... referenced by a foreign key constraint" vì 2 bảng pivot đó vẫn còn.  Cũng sửa unique index (department_id, name) trên evaluation_criteria — MySQL có thể đã dùng unique index này làm supporting index cho FK department_id (thay vì index đơn tự sinh), nên dropUnique() báo lỗi 1553; thêm lại index đơn trước khi drop (idempotent).  Đã test: migrate:fresh --seed chạy sạch (khuyến nghị dùng lệnh này thay migrate:refresh khi cần seed lại từ đầu — không gọi down() của lịch sử migration cũ, tránh toàn bộ nhóm lỗi rollback-ordering này).  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để rollback đối xứng khi lùi 1 bước vì 2 bảng pivot đó vẫn còn vì index đơn tự sinh), nên dropUnique() báo lỗi 1553 |
| **Tác dụng** | rollback đối xứng khi lùi 1 bước; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 15:36 | `edbd53c` | Sửa | Evaluation/2026_08_24_100001_create_evaluation_criteria_table.php, Evaluation/2026_08_26_120001_create_evaluation_positions_table.php |

### 1.5. Thu hẹp DatabaseSeeder chỉ còn quyền + nhân sự

Nền tảng · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Thu hẹp DatabaseSeeder chỉ còn quyền + nhân sự. --seed giờ chỉ chạy RoleSeeder, DepartmentSeeder, SuperAdminSeeder, CnttSoftwareTeamSeeder, DemoUserSeeder — bỏ các seeder dữ liệu mẫu/thật khác (Evaluation criteria, Project/Task demo, Credential — bao gồm 22 tài khoản THẬT của công ty) khỏi luồng seed tự động, để seed production an toàn (chỉ quyền + thành viên), tránh nhét nhầm data mẫu/test lên server thật. Các seeder đó vẫn còn file, chạy riêng qua `db:seed --class=...` khi thật sự cần.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | để seed production an toàn (chỉ quyền + thành viên), tránh nhét nhầm data mẫu/test lên server thật |
| **Tác dụng** | seed production an toàn (chỉ quyền + thành viên), tránh nhét nhầm data mẫu/test lên server thật |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 15:40 | `cbb4a1b` | Cấu trúc lại | seeders/DatabaseSeeder.php |

### 1.6. Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả)

Định danh và phân quyền · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc không gắn màn hình, đụng Identity/DepartmentSeeder.php, seeders/DatabaseSeeder.php. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 15:41 | `17f3e5a` | Sửa tiếp | Identity/DepartmentSeeder.php, seeders/DatabaseSeeder.php |

### 1.7. Sửa tiếp phần Tổng quan (chưa ghi câu mô tả)

Tổng quan · Bảng tin, Công việc và dự án, Tổng quan của tôi · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Bảng tin, Công việc và dự án, Tổng quan của tôi, đụng Dashboard/CompanyDashboardService.php, Dashboard/DepartmentDashboardService.php, Dashboard/BarChart.vue, Dashboard/DonutChart.vue (+6). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 16:59 | `0ab86f5` | Sửa tiếp | Dashboard/CompanyDashboardService.php, Dashboard/DepartmentDashboardService.php, Dashboard/BarChart.vue, Dashboard/DonutChart.vue (+6) |

### 1.8. Sửa tiếp phần Dự án và công việc (chưa ghi câu mô tả)

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Công việc và dự án, đụng Project/TaskController.php, Project/ProjectQuickActionModals.vue, Project/manager.php, Project/ProjectStructureAndBulkTasksTest.php. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 17:00 | `94a53ba` | Sửa tiếp | Project/TaskController.php, Project/ProjectQuickActionModals.vue, Project/manager.php, Project/ProjectStructureAndBulkTasksTest.php |

### 1.9. Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả)

Định danh và phân quyền · Không gắn màn hình · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc không gắn màn hình, đụng .env.example, Identity/GenerateVapidKeysCommand.php, Identity/IdentityServiceProvider.php, Identity/CnttSoftwareTeamSeeder.php (+3). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 17:55 | `384bd96` | Sửa tiếp | .env.example, Identity/GenerateVapidKeysCommand.php, Identity/IdentityServiceProvider.php, Identity/CnttSoftwareTeamSeeder.php (+3) |

### 1.10. Sửa tiếp phần Nền tảng (chưa ghi câu mô tả)

Nền tảng · Không gắn màn hình · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc không gắn màn hình, đụng Console/Kernel.php, Console/Kernel.php. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 17:57 | `d7da3d2` | Sửa tiếp | Console/Kernel.php |
| 18:10 | `955a6f1` | Sửa tiếp | Console/Kernel.php |

### 1.11. Sửa tiếp phần Tổng quan (chưa ghi câu mô tả)

Tổng quan · Công việc và dự án, Tổng quan của tôi, Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Công việc và dự án, Tổng quan của tôi, Cấu hình menu, đụng Dashboard/MyDashboardController.php, Dashboard/DashboardServiceProvider.php, Dashboard/DepartmentDashboardRepositoryInterface.php, Dashboard/MyDashboardRepositoryInterface.php (+25). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 19:02 | `02ab869` | Sửa tiếp | Dashboard/MyDashboardController.php, Dashboard/DashboardServiceProvider.php, Dashboard/DepartmentDashboardRepositoryInterface.php, Dashboard/MyDashboardRepositoryInterface.php (+25) |

### 1.12. Updates task tiến độ

Dự án và công việc · Công việc và dự án · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Updates task tiến độ |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 19:13 | `5d4cd55` | Cập nhật | Project/TaskService.php, Project/TaskQuickActionModals.vue, Project/TaskRowContextMenu.vue |

