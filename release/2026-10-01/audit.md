# Đối soát 01/10/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W40](../summary/2026-W40-summary.md) · Bảng tuần: [2026-W40-audit](../summary/2026-W40-audit.md)

## Việc trong ngày

| # | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu |
| --- | --- | --- | --- | --- | ---: | --- |
| 1 | Sửa tiếp | Kiểm thử | Không gắn màn hình | Sửa tiếp phần Kiểm thử (chưa ghi câu mô tả) | 1 | Không |
| 2 | Sửa tiếp | Dự án và công việc | Đăng nhập, Tiêu chí đánh giá, Công việc và dự án, Tổng quan của tôi | Sửa tiếp phần Dự án và công việc (chưa ghi câu mô tả) | 1 | Không |
| 3 | Sửa tiếp | Định danh và phân quyền | Đăng nhập | Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) | 1 | Không |
| 4 | Sửa tiếp | Khác | Quyền và nhật ký, Cấu hình menu | Sửa tiếp phần Khác (chưa ghi câu mô tả) | 3 | Đổi cấu trúc dữ liệu |
| 5 | Sửa tiếp | Cấu hình workspace | Cấu hình menu | Sửa tiếp phần Cấu hình workspace (chưa ghi câu mô tả) | 1 | Không |
| 6 | Sửa tiếp | Định danh và phân quyền | Quyền và nhật ký, Cấu hình menu | Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) | 3 | Đổi cấu trúc dữ liệu |
| 7 | Sửa tiếp | Dự án và công việc | Quyền và nhật ký, Công việc và dự án, Cấu hình menu | Sửa tiếp phần Dự án và công việc (chưa ghi câu mô tả) | 2 | Không |

## Từng lần lưu

| Giờ | Mã | Loại | Phần | Màn hình | Đã ghi | Chỗ sửa |
| --- | --- | --- | --- | --- | --- | --- |
| 10:09 | `4945e8a` | Sửa tiếp | Kiểm thử | Không gắn màn hình | Sửa tiếp cùng việc. | Report/ReportPeopleSnapshotTest.php |
| 10:13 | `c901aa4` | Sửa tiếp | Dự án và công việc | Đăng nhập, Tiêu chí đánh giá, Công việc và dự án, Tổng quan của tôi | Sửa tiếp cùng việc. Màn: Đăng nhập, Tiêu chí đánh giá, Công việc và dự án, Tổng quan của tôi. | Dashboard/CompanyDashboardService.php, Identity/GoogleAuthController.php, Identity/Login.vue, Identity/web.php (+31) |
| 10:28 | `d68dbf4` | Sửa tiếp | Định danh và phân quyền | Đăng nhập | Sửa tiếp cùng việc. Màn: Đăng nhập. | Identity/GoogleAuthController.php, Identity/Login.vue, Identity/web.php |
| 11:16 | `479fad3` | Sửa tiếp | Khác | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | Contract/ContractEnums.php, Contract/SupplierController.php, Contract/SupplierDocumentController.php, Contract/ChangeSupplierStatusRequest.php (+30) |
| 11:21 | `eee3911` | Sửa tiếp | Khác | Không gắn màn hình | Sửa tiếp cùng việc. | Contract/2026_10_01_110001_create_contract_supplier_tables.php, Contract/SupplierApiTest.php |
| 11:26 | `d52dd62` | Sửa tiếp | Khác | Quyền và nhật ký, Cấu hình menu | Sửa tiếp cùng việc. Màn: Quyền và nhật ký, Cấu hình menu. | Contract/2026_10_01_110001_create_contract_supplier_tables.php, Identity/PermissionMatrix.vue, WorkspaceConfig/WorkspaceConfigUnassignedMembersSuperadmin.vue |
| 11:30 | `4a1adec` | Sửa tiếp | Cấu hình workspace | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | WorkspaceConfig/WorkspaceConfigMemberService.php, config/permissions.php, Project/ProjectVisibilityTest.php, WorkspaceConfig/WorkspaceConfigOverviewTest.php |
| 11:33 | `6052210` | Sửa tiếp | Định danh và phân quyền | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | Identity/HrmSsoService.php, Identity/AuthenticatedUserPresenter.php, Identity/DefaultMemberRoleBootstrap.php, Identity/GoogleAuthenticator.php (+3) |
| 11:42 | `9ea74f1` | Sửa tiếp | Định danh và phân quyền | Quyền và nhật ký, Cấu hình menu | Sửa tiếp cùng việc. Màn: Quyền và nhật ký, Cấu hình menu. | .env.example, Identity/ProcessOrgUnitChangedJob.php, Identity/HrmDepartmentSyncService.php, Identity/TeamController.php (+14) |
| 11:43 | `8b057f6` | Sửa tiếp | Định danh và phân quyền | Quyền và nhật ký | Sửa tiếp cùng việc. Màn: Quyền và nhật ký. | Identity/PermissionMatrix.vue |
| 11:55 | `ec86528` | Sửa tiếp | Dự án và công việc | Quyền và nhật ký, Công việc và dự án, Cấu hình menu | Sửa tiếp cùng việc. Màn: Quyền và nhật ký, Công việc và dự án, Cấu hình menu. | Identity/PermissionMatrix.vue, Project/ProjectRepository.php, Project/ProjectService.php, config/permissions.php (+2) |
| 11:59 | `0f50ecf` | Sửa tiếp | Dự án và công việc | Công việc và dự án | Sửa tiếp cùng việc. Màn: Công việc và dự án. | Project/ProjectRepository.php |

