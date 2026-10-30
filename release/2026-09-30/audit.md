# Đối soát 30/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W40](../summary/2026-W40-summary.md) · Bảng tuần: [2026-W40-audit](../summary/2026-W40-audit.md)

## Việc trong ngày

| # | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu |
| --- | --- | --- | --- | --- | ---: | --- |
| 1 | Sửa tiếp | Định danh và phân quyền | Đăng nhập | Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) | 1 | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| 2 | Cập nhật | Định danh và phân quyền | Đăng nhập | Trang login: quay lại nút icon tròn, thêm tên hệ thống dưới logo | 1 | Không |
| 3 | Cập nhật | Định danh và phân quyền | Đăng nhập | Trang login: đổi tên hệ thống và icon nút cho đẹp hơn | 1 | Không |
| 4 | Cập nhật | Định danh và phân quyền | Cấu hình menu | Ghi log lý do JWT SSO không hợp lệ trong HrmSsoController::callback() | 5 | Không |
| 5 | Sửa tiếp | Cấu hình workspace | Cấu hình menu | Sửa tiếp phần Cấu hình workspace (chưa ghi câu mô tả) | 2 | Không |
| 6 | Sửa tiếp | Định danh và phân quyền | Cấu hình menu | Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) | 2 | Không |
| 7 | Sửa tiếp | Cấu hình workspace | Cấu hình menu | Sửa tiếp phần Cấu hình workspace (chưa ghi câu mô tả) | 1 | Không |
| 8 | Sửa tiếp | Đề xuất | Quyền và nhật ký | Sửa tiếp phần Đề xuất (chưa ghi câu mô tả) | 1 | Không |

## Từng lần lưu

| Giờ | Mã | Loại | Phần | Màn hình | Đã ghi | Chỗ sửa |
| --- | --- | --- | --- | --- | --- | --- |
| 08:43 | `d350f4c` | Sửa tiếp | Định danh và phân quyền | Đăng nhập | Sửa tiếp cùng việc. Màn: Đăng nhập. | .env.example, Identity/WarmHrmJwksCommand.php, Identity/HrmAssignmentData.php, Identity/HrmEmployeeData.php (+43) |
| 09:02 | `364c20e` | Cập nhật | Định danh và phân quyền | Đăng nhập | Trang login: quay lại nút icon tròn, thêm tên hệ thống dưới logo | Identity/Login.vue |
| 09:07 | `a035720` | Cập nhật | Định danh và phân quyền | Đăng nhập | Trang login: đổi tên hệ thống và icon nút cho đẹp hơn | Identity/Login.vue |
| 09:34 | `2ba87d0` | Cập nhật | Định danh và phân quyền | Không gắn màn hình | Ghi log lý do JWT SSO không hợp lệ trong HrmSsoController::callback() | Identity/HrmSsoController.php |
| 09:38 | `278279f` | Sửa tiếp | Định danh và phân quyền | Không gắn màn hình | Sửa tiếp cùng việc. | .env.example, Identity/HrmApiClient.php, Identity/HrmJwtVerifier.php, Identity/HrmOutboundHttp.php (+4) |
| 09:41 | `ce6113e` | Sửa tiếp | Định danh và phân quyền | Không gắn màn hình | Sửa tiếp cùng việc. | .env.example, Identity/HrmApiClient.php, Identity/HrmSsoController.php, Identity/HrmApiClientTest.php |
| 09:50 | `5148035` | Sửa tiếp | Định danh và phân quyền | Không gắn màn hình | Sửa tiếp cùng việc. | .env.example, Identity/DiagnoseHrmSsoCommand.php, Identity/HrmApiClient.php, Identity/HrmSsoController.php (+1) |
| 10:19 | `5a6c448` | Sửa tiếp | Định danh và phân quyền | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | Identity/HrmApiClient.php, Identity/HrmDepartmentSyncService.php, Identity/UserRepositoryInterface.php, Identity/UserRepository.php (+6) |
| 10:21 | `05ed2e9` | Sửa tiếp | Cấu hình workspace | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | WorkspaceConfig/WorkspaceConfigMemberService.php, WorkspaceConfig/overview.js, WorkspaceConfig/WorkspaceConfigOverviewSuperadmin.vue, WorkspaceConfig/WorkspaceConfigOverviewTest.php |
| 10:32 | `378df1c` | Sửa tiếp | Cấu hình workspace | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | WorkspaceConfig/unassignedMembers.js, WorkspaceConfig/WorkspaceConfigOverviewSuperadmin.vue, WorkspaceConfig/WorkspaceConfigOverviewTest.php |
| 10:45 | `e07dd3c` | Sửa tiếp | Định danh và phân quyền | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | Identity/HrmAssignmentData.php, Identity/HrmApiClient.php, Identity/HrmEmployeeBulkSyncService.php, Identity/HrmEmployeeSyncService.php (+3) |
| 11:08 | `56ff9ae` | Sửa tiếp | Định danh và phân quyền | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | .env.example, Identity/HrmEmployeeBulkSyncService.php, WorkspaceConfig/WorkspaceConfigUnassignedMembersSuperadmin.vue, config/services.php (+1) |
| 11:26 | `d52ba0e` | Sửa tiếp | Cấu hình workspace | Cấu hình menu | Sửa tiếp cùng việc. Màn: Cấu hình menu. | WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/ColumnHeaderFilter.vue, WorkspaceConfig/MemberKpiStrip.vue, WorkspaceConfig/unassignedMembers.js (+2) |
| 13:27 | `3e30e43` | Sửa tiếp | Đề xuất | Quyền và nhật ký | Sửa tiếp cùng việc. Màn: Quyền và nhật ký. | FeatureRequest/FeatureRequestController.php, FeatureRequest/ExportFeatureRequestRequest.php, FeatureRequest/FeatureRequestExcelExporter.php, FeatureRequest/FeatureRequestService.php (+5) |

