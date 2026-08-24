# Module WorkspaceConfig

Hub cấu hình scoped theo phòng ban (xem `docs/VA_WORKSPACE_OVERVIEW.md` §2, §7.1,
§10, §21 — Phase 1b xong, Phase 1c = Evaluation). department_director /
deputy_department_director vào 1 trang hub duy nhất để tự quản lý "workspace con"
của phòng ban mình:

- **Thành viên** — nhân sự phòng ban, CRUD nhóm, gán vai trò (đã có).
- **Menu (sidebar)** — bật/tắt mục menu áp dụng riêng cho phòng ban (đã có).
- **Tiêu chí đánh giá** (Giai đoạn B, module `Evaluation` — **chưa có**, làm tiếp).

super_admin xem TỔNG HỢP workspace của mọi phòng ban (1 bảng liệt kê + bấm
vào xem chi tiết từng phòng ban), nhưng không sửa thay department_director.

## Phân quyền

- `workspace_config.view_department` — xem hub (thành viên, menu) của phòng
  ban mình. Gán cho `department_director`/`deputy_department_director`.
- `workspace_config.manage_sidebar_department` — bật/tắt menu sidebar của
  phòng ban mình. Gán cho `department_director`/`deputy_department_director`.
- `workspace_config.view_all` — xem tổng hợp mọi phòng ban. Reserved, chỉ
  `super_admin`.

Cả 2 route manager (`/manager/workspace-config/*`) đều lấy `department_id`
từ **`$request->user()->department_id`**, KHÔNG nhận từ query/body — khác
`TeamController` (nơi 1 user có thể quản nhiều phòng ban) vì ở đây 1 trưởng
phòng chỉ được xem/sửa đúng phòng ban của chính mình.

## Cấu trúc

```
Modules/WorkspaceConfig/
├── App/
│   ├── Http/Controllers/     # WorkspaceConfigMemberController, WorkspaceConfigSidebarController,
│   │                          # WorkspaceConfigOverviewController (superadmin)
│   ├── Http/Requests/
│   ├── Services/              # WorkspaceConfigMemberService, DepartmentSidebarConfigService
│   └── Providers/             # WorkspaceConfigServiceProvider
├── routes/
│   ├── manager.php             # thành viên + sidebar, scope = phòng ban user hiện tại
│   └── superadmin.php          # tổng hợp mọi phòng ban
└── resources/js/pages/
    ├── WorkspaceConfigHub.vue                       # entry point sidebar, tab điều hướng nội bộ
    ├── WorkspaceConfigMembers.vue
    ├── WorkspaceConfigSidebar.vue
    ├── WorkspaceConfigOverviewSuperadmin.vue
    └── WorkspaceConfigDepartmentDetailSuperadmin.vue
```

Model/Migration/Repository của bảng `department_sidebar_configs` đặt trong
`Modules/Identity` (gắn chặt vòng đời User/Department, theo tiền lệ `Team`)
— module này chỉ chứa Controller/Service điều phối UI, gọi lại
`DepartmentSidebarConfigRepositoryInterface` và `UserRepositoryInterface`
của Identity, giống cách nó không có Repository riêng cho phần thành viên.

## Design pattern

**Controller → Service → Repository (interface) → Eloquent Model** — không
có ngoại lệ, kể cả 2 controller mỏng trong module này.

## Việc tiếp theo (Giai đoạn B — **làm ngay**, xem overview §21)

- Module `Evaluation` riêng: tiêu chí đánh giá tự tạo (2 kiểu — thang điểm
  nhiều mức và cộng/trừ theo hành vi), thêm 1 tab "Tiêu chí đánh giá" vào
  `WorkspaceConfigHub.vue`. Không thêm mục sidebar riêng.
- Super_admin: chỉ xem tiêu chí trên trang chi tiết phòng ban, không sửa thay
  trưởng phòng.

Giai đoạn C (sau, không phải đợt này): mẫu đánh giá + phiếu đánh giá đầy
đủ (hội đồng nhiều vai trò, % trọng số, kỳ đánh giá) — xem
`docs/VA_WORKSPACE_OVERVIEW.md` §7 và §21.
