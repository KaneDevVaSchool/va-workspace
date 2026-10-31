# Module DonTu — đơn từ hành chính

Trạng thái **2026-10-31**: cấu hình backend + màn admin. Sidebar chỉ còn **Cấu hình** (`/manager/don-tu` → `manager.don-tu.config`, `DonTuConfigStatusHub.vue`, `meta.title` Cấu hình đơn từ). Nộp đơn, hồ sơ của tôi, duyệt, tra cứu, giám sát vẫn là `DonTuPlaceholder.vue` và **không** hiện trên menu.

## Đã có

- Migration master: workflow, bước, trạng thái, mẫu biểu (`Modules/DonTu/Database/migrations/`).
- API manager: workflow, status set, form template (`Modules/DonTu/routes/manager.php`).
- Vue admin: hub cấu hình (`DonTuConfigOverview.vue`, `DonTuWorkflows.vue`, `DonTuFormTemplates.vue`, …), sidebar từ `nav.js`.
- Seed demo: `DonTuConfigDemoSeeder`, workflow/form mẫu.

## Chưa có

- Màn nộp đơn, hàng đợi duyệt, PDF/in ấn theo mẫu — route compose/mine/show trỏ `DonTuPlaceholder.vue` («Chưa có biểu mẫu, danh sách hay thao tác duyệt»).
- Module **chưa** liệt kê trong `VA_WORKSPACE_OVERVIEW.md` §2 — cần bổ sung khi go-live luồng nộp.

Thiết kế chi tiết (draft): [plans/2026-10-26-don-tu-detailed-design.md](../../../plans/2026-10-26-don-tu-detailed-design.md).
