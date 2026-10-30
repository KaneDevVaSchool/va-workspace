# Đối soát 14/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W38](../summary/2026-W38-summary.md) · Bảng tuần: [2026-W38-audit](../summary/2026-W38-audit.md)

## Việc trong ngày

| # | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu |
| --- | --- | --- | --- | --- | ---: | --- |
| 1 | Thêm | Tổng quan | Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập, Tổng quan của tôi, Cấu hình menu | Seed nhân sự CNTT, xuất Excel Credential, quản lý task nhanh, cấu hình menu sidebar | 1 | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| 2 | Sửa | Dự án và công việc | Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập | Drop đúng tên FK tường minh khi rollback 3 migration | 2 | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi bảng công việc |
| 3 | Sửa tiếp | Đánh giá | Không gắn màn hình | Sửa tiếp phần Đánh giá (chưa ghi câu mô tả) | 2 | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| 4 | Sửa | Đánh giá | Không gắn màn hình | Drop bảng pivot mồ côi trước khi rollback evaluation_criteria/positions | 1 | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| 5 | Cấu trúc lại | Nền tảng | Không gắn màn hình | Thu hẹp DatabaseSeeder chỉ còn quyền + nhân sự | 1 | Không |
| 6 | Sửa tiếp | Định danh và phân quyền | Không gắn màn hình | Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) | 1 | Không |
| 7 | Sửa tiếp | Tổng quan | Bảng tin, Công việc và dự án, Tổng quan của tôi | Sửa tiếp phần Tổng quan (chưa ghi câu mô tả) | 1 | Không |
| 8 | Sửa tiếp | Dự án và công việc | Công việc và dự án | Sửa tiếp phần Dự án và công việc (chưa ghi câu mô tả) | 1 | Không |
| 9 | Sửa tiếp | Định danh và phân quyền | Không gắn màn hình | Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) | 1 | Không |
| 10 | Sửa tiếp | Nền tảng | Không gắn màn hình | Sửa tiếp phần Nền tảng (chưa ghi câu mô tả) | 2 | Không |
| 11 | Sửa tiếp | Tổng quan | Công việc và dự án, Tổng quan của tôi, Cấu hình menu | Sửa tiếp phần Tổng quan (chưa ghi câu mô tả) | 1 | Đổi cấu trúc dữ liệu |
| 12 | Cập nhật | Dự án và công việc | Công việc và dự án | Updates task tiến độ | 1 | Không |

## Từng lần lưu

| Giờ | Mã | Loại | Phần | Màn hình | Đã ghi | Chỗ sửa |
| --- | --- | --- | --- | --- | --- | --- |
| 14:54 | `1d1d158` | Thêm | Tổng quan | Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập, Tổng quan của tôi, Cấu hình menu | Seed nhân sự CNTT, xuất Excel Credential, quản lý task nhanh, cấu hình menu sidebar | Credential/CredentialEnums.php, Credential/CredentialController.php, Credential/CredentialViewerController.php, Credential/StoreCredentialRequest.php (+108) |
| 15:05 | `44ea7ae` | Sửa | Dự án và công việc | Công việc và dự án | Drop đúng tên FK tường minh khi rollback 3 migration | Project/2026_08_27_100005_add_departments_to_projects_table.php, Project/2026_08_28_100001_upgrade_project_create_fields.php, Project/2026_09_09_100002_add_folder_id_to_project_attachments_table.php |
| 15:30 | `225b03b` | Sửa tiếp | Dự án và công việc | Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập | Sửa tiếp cùng việc. Màn: Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập. | Credential/CredentialProviderService.php, Credential/CredentialService.php, Evaluation/EvaluationCriteriaController.php, Identity/ShortcutController.php (+22) |
| 15:32 | `704e310` | Sửa tiếp | Đánh giá | Không gắn màn hình | Sửa tiếp cùng việc. | Evaluation/2026_08_26_150001_add_unique_name_per_department_to_evaluation_criteria_table.php |
| 15:34 | `c0a4bf4` | Sửa tiếp | Đánh giá | Không gắn màn hình | Sửa tiếp cùng việc. | Evaluation/2026_08_26_120001_create_evaluation_positions_table.php |
| 15:36 | `edbd53c` | Sửa | Đánh giá | Không gắn màn hình | Drop bảng pivot mồ côi trước khi rollback evaluation_criteria/positions | Evaluation/2026_08_24_100001_create_evaluation_criteria_table.php, Evaluation/2026_08_26_120001_create_evaluation_positions_table.php |
| 15:40 | `cbb4a1b` | Cấu trúc lại | Nền tảng | Không gắn màn hình | Thu hẹp DatabaseSeeder chỉ còn quyền + nhân sự | seeders/DatabaseSeeder.php |
| 15:41 | `17f3e5a` | Sửa tiếp | Định danh và phân quyền | Không gắn màn hình | Sửa tiếp cùng việc. | Identity/DepartmentSeeder.php, seeders/DatabaseSeeder.php |
| 16:59 | `0ab86f5` | Sửa tiếp | Tổng quan | Bảng tin, Công việc và dự án, Tổng quan của tôi | Sửa tiếp cùng việc. Màn: Bảng tin, Công việc và dự án, Tổng quan của tôi. | Dashboard/CompanyDashboardService.php, Dashboard/DepartmentDashboardService.php, Dashboard/BarChart.vue, Dashboard/DonutChart.vue (+6) |
| 17:00 | `94a53ba` | Sửa tiếp | Dự án và công việc | Công việc và dự án | Sửa tiếp cùng việc. Màn: Công việc và dự án. | Project/TaskController.php, Project/ProjectQuickActionModals.vue, Project/manager.php, Project/ProjectStructureAndBulkTasksTest.php |
| 17:55 | `384bd96` | Sửa tiếp | Định danh và phân quyền | Không gắn màn hình | Sửa tiếp cùng việc. | .env.example, Identity/GenerateVapidKeysCommand.php, Identity/IdentityServiceProvider.php, Identity/CnttSoftwareTeamSeeder.php (+3) |
| 17:57 | `d7da3d2` | Sửa tiếp | Nền tảng | Không gắn màn hình | Sửa tiếp cùng việc. | Console/Kernel.php |
| 18:10 | `955a6f1` | Sửa tiếp | Nền tảng | Không gắn màn hình | Sửa tiếp cùng việc. | Console/Kernel.php |
| 19:02 | `02ab869` | Sửa tiếp | Tổng quan | Công việc và dự án, Tổng quan của tôi, Cấu hình menu | Sửa tiếp cùng việc. Màn: Công việc và dự án, Tổng quan của tôi, Cấu hình menu. | Dashboard/MyDashboardController.php, Dashboard/DashboardServiceProvider.php, Dashboard/DepartmentDashboardRepositoryInterface.php, Dashboard/MyDashboardRepositoryInterface.php (+25) |
| 19:13 | `5d4cd55` | Cập nhật | Dự án và công việc | Công việc và dự án | Updates task tiến độ | Project/TaskService.php, Project/TaskQuickActionModals.vue, Project/TaskRowContextMenu.vue |

