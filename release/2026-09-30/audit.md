# Đối soát 30/09/2026

Đọc nhanh: [README.md](./README.md) · Tuần: [2026-W40](../summary/2026-W40-summary.md) · Bảng tuần: [2026-W40-audit](../summary/2026-W40-audit.md)

Việc trong ngày đánh số **1.1**, **1.2**, … Mỗi lần lưu (commit) là **1.1.1**, **1.1.2**, … Bảng có thêm dòng **Giải thích đơn giản** cho người không rành kỹ thuật. Thiếu trong commit thì ghi «chưa ghi», không suy diễn.

## Việc trong ngày

### 1.1. Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả)

Định danh và phân quyền · Đăng nhập · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Đăng nhập, đụng .env.example, Identity/WarmHrmJwksCommand.php, Identity/HrmAssignmentData.php, Identity/HrmEmployeeData.php (+43). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Định danh và phân quyền trên màn Đăng nhập. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: .env.example, Identity/WarmHrmJwksCommand.php, Identity/HrmAssignmentData.php, Identity/HrmEmployeeData.php (+43). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 08:43 | `d350f4c` | Sửa tiếp | .env.example, Identity/WarmHrmJwksCommand.php, Identity/HrmAssignmentData.php, Identity/HrmEmployeeData.php (+43) |

#### Chi tiết từng lần lưu (version)

##### Version 1.1.1 — mã `d350f4c` · 08:43

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Đăng nhập. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu |
| **Giải thích đơn giản** | Sửa nốt phần Định danh và phân quyền trên màn Đăng nhập. Khi đưa lên máy chủ cần chạy cập nhật dữ liệu (Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu). File chính: .env.example, Identity/WarmHrmJwksCommand.php, Identity/HrmAssignmentData.php, Identity/HrmEmployeeData.php (+43). |

### 1.2. Trang login: quay lại nút icon tròn, thêm tên hệ thống dưới logo

Định danh và phân quyền · Đăng nhập · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Trang login: quay lại nút icon tròn, thêm tên hệ thống dưới logo. Nút login (giờ trỏ /auth/hrm/redirect) đổi từ dạng nút chữ nền đỏ về lại kiểu icon tròn như trước đây, và thêm dòng tên hệ thống dưới logo.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Trang login: quay lại nút icon tròn, thêm tên hệ thống dưới logo. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/Login.vue. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:02 | `364c20e` | Cập nhật | Identity/Login.vue |

#### Chi tiết từng lần lưu (version)

##### Version 1.2.1 — mã `364c20e` · 09:02

| | |
| --- | --- |
| **Đổi gì** | Trang login: quay lại nút icon tròn, thêm tên hệ thống dưới logo. Nút login (giờ trỏ /auth/hrm/redirect) đổi từ dạng nút chữ nền đỏ về lại kiểu icon tròn như trước đây, và thêm dòng tên hệ thống dưới logo.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Trang login: quay lại nút icon tròn, thêm tên hệ thống dưới logo. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/Login.vue. |

### 1.3. Trang login: đổi tên hệ thống và icon nút cho đẹp hơn

Định danh và phân quyền · Đăng nhập · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Trang login: đổi tên hệ thống và icon nút cho đẹp hơn. Tên hệ thống viết hoa + dùng dấu & thay "và", icon nút login đổi lại dùng logo Google (đã có sẵn trong public/images) thay vì favicon.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì favicon |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Trang login: đổi tên hệ thống và icon nút cho đẹp hơn. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/Login.vue. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:07 | `a035720` | Cập nhật | Identity/Login.vue |

#### Chi tiết từng lần lưu (version)

##### Version 1.3.1 — mã `a035720` · 09:07

| | |
| --- | --- |
| **Đổi gì** | Trang login: đổi tên hệ thống và icon nút cho đẹp hơn. Tên hệ thống viết hoa + dùng dấu & thay "và", icon nút login đổi lại dùng logo Google (đã có sẵn trong public/images) thay vì favicon.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | vì favicon |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Trang login: đổi tên hệ thống và icon nút cho đẹp hơn. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/Login.vue. |

### 1.4. Ghi log lý do JWT SSO không hợp lệ trong HrmSsoController::callback()

Định danh và phân quyền · Cấu hình menu · 5 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Ghi log lý do JWT SSO không hợp lệ trong HrmSsoController::callback(). Trước đây bắt HrmTokenInvalid/audience mismatch nhưng không log gì, không debug được lý do thật khi callback báo lỗi "không hợp lệ" — thêm Log::warning kèm message/context cụ thể.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | do JWT SSO không hợp lệ trong HrmSsoController::callback() do thật khi callback báo lỗi "không hợp lệ" — thêm Log::warning kèm message/context cụ thể |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 5 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 09:34 | `2ba87d0` | Cập nhật | Identity/HrmSsoController.php |
| 09:38 | `278279f` | Sửa tiếp | .env.example, Identity/HrmApiClient.php, Identity/HrmJwtVerifier.php, Identity/HrmOutboundHttp.php (+4) |
| 09:41 | `ce6113e` | Sửa tiếp | .env.example, Identity/HrmApiClient.php, Identity/HrmSsoController.php, Identity/HrmApiClientTest.php |
| 09:50 | `5148035` | Sửa tiếp | .env.example, Identity/DiagnoseHrmSsoCommand.php, Identity/HrmApiClient.php, Identity/HrmSsoController.php (+1) |
| 10:19 | `5a6c448` | Sửa tiếp | Identity/HrmApiClient.php, Identity/HrmDepartmentSyncService.php, Identity/UserRepositoryInterface.php, Identity/UserRepository.php (+6) |

#### Chi tiết từng lần lưu (version)

##### Version 1.4.1 — mã `2ba87d0` · 09:34

| | |
| --- | --- |
| **Đổi gì** | Ghi log lý do JWT SSO không hợp lệ trong HrmSsoController::callback(). Trước đây bắt HrmTokenInvalid/audience mismatch nhưng không log gì, không debug được lý do thật khi callback báo lỗi "không hợp lệ" — thêm Log::warning kèm message/context cụ thể.  Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com> |
| **Vì sao** | do JWT SSO không hợp lệ trong HrmSsoController::callback() Trước đây bắt HrmTokenInvalid/audience mismatch nhưng không log gì, không debug được lý do thật khi callback báo lỗi "không hợp lệ" — thêm Log::warning kèm message/context cụ thể |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Ghi log lý do JWT SSO không hợp lệ trong HrmSsoController::callback(). Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/HrmSsoController.php. |

##### Version 1.4.2 — mã `278279f` · 09:38

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Định danh và phân quyền. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: .env.example, Identity/HrmApiClient.php, Identity/HrmJwtVerifier.php, Identity/HrmOutboundHttp.php (+4). |

##### Version 1.4.3 — mã `ce6113e` · 09:41

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Định danh và phân quyền. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: .env.example, Identity/HrmApiClient.php, Identity/HrmSsoController.php, Identity/HrmApiClientTest.php. |

##### Version 1.4.4 — mã `5148035` · 09:50

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Định danh và phân quyền. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: .env.example, Identity/DiagnoseHrmSsoCommand.php, Identity/HrmApiClient.php, Identity/HrmSsoController.php (+1). |

##### Version 1.4.5 — mã `5a6c448` · 10:19

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Định danh và phân quyền trên màn Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/HrmApiClient.php, Identity/HrmDepartmentSyncService.php, Identity/UserRepositoryInterface.php, Identity/UserRepository.php (+6). |

### 1.5. Sửa tiếp phần Cấu hình workspace (chưa ghi câu mô tả)

Cấu hình workspace · Cấu hình menu · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Cấu hình menu, đụng WorkspaceConfig/WorkspaceConfigMemberService.php, WorkspaceConfig/overview.js, WorkspaceConfig/WorkspaceConfigOverviewSuperadmin.vue, WorkspaceConfig/WorkspaceConfigOverviewTest.php (+3). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 2 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:21 | `05ed2e9` | Sửa tiếp | WorkspaceConfig/WorkspaceConfigMemberService.php, WorkspaceConfig/overview.js, WorkspaceConfig/WorkspaceConfigOverviewSuperadmin.vue, WorkspaceConfig/WorkspaceConfigOverviewTest.php |
| 10:32 | `378df1c` | Sửa tiếp | WorkspaceConfig/unassignedMembers.js, WorkspaceConfig/WorkspaceConfigOverviewSuperadmin.vue, WorkspaceConfig/WorkspaceConfigOverviewTest.php |

#### Chi tiết từng lần lưu (version)

##### Version 1.5.1 — mã `05ed2e9` · 10:21

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Cấu hình workspace trên màn Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: WorkspaceConfig/WorkspaceConfigMemberService.php, WorkspaceConfig/overview.js, WorkspaceConfig/WorkspaceConfigOverviewSuperadmin.vue, WorkspaceConfig/WorkspaceConfigOverviewTest.php. |

##### Version 1.5.2 — mã `378df1c` · 10:32

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Cấu hình workspace trên màn Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: WorkspaceConfig/unassignedMembers.js, WorkspaceConfig/WorkspaceConfigOverviewSuperadmin.vue, WorkspaceConfig/WorkspaceConfigOverviewTest.php. |

### 1.6. Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả)

Định danh và phân quyền · Cấu hình menu · 2 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Cấu hình menu, đụng Identity/HrmAssignmentData.php, Identity/HrmApiClient.php, Identity/HrmEmployeeBulkSyncService.php, Identity/HrmEmployeeSyncService.php (+8). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Gom 2 lần lưu cùng một việc; xem bảng từng version bên dưới. |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 10:45 | `e07dd3c` | Sửa tiếp | Identity/HrmAssignmentData.php, Identity/HrmApiClient.php, Identity/HrmEmployeeBulkSyncService.php, Identity/HrmEmployeeSyncService.php (+3) |
| 11:08 | `56ff9ae` | Sửa tiếp | .env.example, Identity/HrmEmployeeBulkSyncService.php, WorkspaceConfig/WorkspaceConfigUnassignedMembersSuperadmin.vue, config/services.php (+1) |

#### Chi tiết từng lần lưu (version)

##### Version 1.6.1 — mã `e07dd3c` · 10:45

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Định danh và phân quyền trên màn Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: Identity/HrmAssignmentData.php, Identity/HrmApiClient.php, Identity/HrmEmployeeBulkSyncService.php, Identity/HrmEmployeeSyncService.php (+3). |

##### Version 1.6.2 — mã `56ff9ae` · 11:08

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Khác trên màn Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: .env.example, Identity/HrmEmployeeBulkSyncService.php, WorkspaceConfig/WorkspaceConfigUnassignedMembersSuperadmin.vue, config/services.php (+1). |

### 1.7. Sửa tiếp phần Cấu hình workspace (chưa ghi câu mô tả)

Cấu hình workspace · Cấu hình menu · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Cấu hình menu, đụng WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/ColumnHeaderFilter.vue, WorkspaceConfig/MemberKpiStrip.vue, WorkspaceConfig/unassignedMembers.js (+2). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Cấu hình workspace trên màn Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/ColumnHeaderFilter.vue, WorkspaceConfig/MemberKpiStrip.vue, WorkspaceConfig/unassignedMembers.js (+2). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 11:26 | `d52ba0e` | Sửa tiếp | WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/ColumnHeaderFilter.vue, WorkspaceConfig/MemberKpiStrip.vue, WorkspaceConfig/unassignedMembers.js (+2) |

#### Chi tiết từng lần lưu (version)

##### Version 1.7.1 — mã `d52ba0e` · 11:26

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Cấu hình menu. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Cấu hình workspace trên màn Cấu hình menu. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: WorkspaceConfig/GlobalMenuVisibilityService.php, WorkspaceConfig/ColumnHeaderFilter.vue, WorkspaceConfig/MemberKpiStrip.vue, WorkspaceConfig/unassignedMembers.js (+2). |

### 1.8. Sửa tiếp phần Đề xuất (chưa ghi câu mô tả)

Đề xuất · Quyền và nhật ký · 1 lần lưu

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc trên màn Quyền và nhật ký, đụng FeatureRequest/FeatureRequestController.php, FeatureRequest/ExportFeatureRequestRequest.php, FeatureRequest/FeatureRequestExcelExporter.php, FeatureRequest/FeatureRequestService.php (+5). |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Đề xuất trên màn Quyền và nhật ký. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: FeatureRequest/FeatureRequestController.php, FeatureRequest/ExportFeatureRequestRequest.php, FeatureRequest/FeatureRequestExcelExporter.php, FeatureRequest/FeatureRequestService.php (+5). |

| Giờ | Mã | Loại | Chỗ sửa |
| --- | --- | --- | --- |
| 13:27 | `3e30e43` | Sửa tiếp | FeatureRequest/FeatureRequestController.php, FeatureRequest/ExportFeatureRequestRequest.php, FeatureRequest/FeatureRequestExcelExporter.php, FeatureRequest/FeatureRequestService.php (+5) |

#### Chi tiết từng lần lưu (version)

##### Version 1.8.1 — mã `3e30e43` · 13:27

| | |
| --- | --- |
| **Đổi gì** | Sửa tiếp cùng việc. Màn: Quyền và nhật ký. |
| **Vì sao** | Chưa ghi lý do trong commit |
| **Tác dụng** | Chưa ghi tác dụng trong commit |
| **Giải thích đơn giản** | Sửa nốt phần Đề xuất trên màn Quyền và nhật ký. Không đổi cấu trúc dữ liệu — chỉ cần bản phần mềm mới. File chính: FeatureRequest/FeatureRequestController.php, FeatureRequest/ExportFeatureRequestRequest.php, FeatureRequest/FeatureRequestExcelExporter.php, FeatureRequest/FeatureRequestService.php (+5). |

