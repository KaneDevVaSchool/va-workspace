# FeatureRequest

Ghi nhận yêu cầu tính năng từ nhân viên (nút tam giác đỏ ở `AppHeader`), superadmin
theo dõi và xử lý theo phòng ban.

Luồng trạng thái: `pending` (chờ ghi nhận) → `reviewing` (đang xem xét, tự chuyển
khi superadmin mở chi tiết) → `approved` (đã duyệt, kèm ngày hoàn thành dự kiến +
ghi chú tiến độ) → `done` (đã hoàn thành). `rejected` có thể xảy ra ở
`pending`/`reviewing`/`approved`.

Quyền: `feature_request.create` (mọi role), `feature_request.review`
(superadmin) — khai báo trong `config/permissions.php`.
