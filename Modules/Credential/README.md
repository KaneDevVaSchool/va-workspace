# Module Credential (Quản lý tài khoản)

Quản lý tài khoản dịch vụ dùng trong công ty: tài khoản Google Workspace nội
bộ, Canva, Cursor, Claude, AWS, VPS, database, root, IAM, domain... Mật khẩu
được mã hoá 2 chiều (Laravel `encrypted` cast), chỉ người tạo và người được
cấp quyền (`credential_viewers`) mới xem được dữ liệu nhạy cảm — người khác
(kể cả super_admin không được cấp) chỉ thấy metadata (nhà cung cấp, loại tài
khoản, trạng thái hạn dùng).

## Cấu trúc

Theo đúng pattern chuẩn `Controller → Service → Repository → Model` của dự
án (xem `.claude/CLAUDE.md` §5) và route JSON dưới prefix `/api` giống
`Modules/Project` (không theo pattern page-serving của `Modules/Example`).

- `credentials` — bảng chính, 1 bản ghi = 1 tài khoản dịch vụ.
- `credential_providers` — danh mục nhà cung cấp, quản lý được qua UI.
- `credential_viewers` — danh sách user được người tạo cấp quyền xem
  password/username thật của 1 credential cụ thể.

## Permission

Dùng key `credential.*` đã có sẵn trong `config/permissions.php`:
- `credential.view` — xem danh sách/chi tiết (chỉ metadata, trừ khi là
  creator/viewer).
- `credential.manage` — tạo/sửa/xoá bản ghi, quản lý danh mục nhà cung cấp.
