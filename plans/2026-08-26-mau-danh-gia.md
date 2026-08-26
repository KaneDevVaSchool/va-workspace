# Mẫu đánh giá — Evaluation Giai đoạn C

> Tiếp theo sau Giai đoạn B (Tiêu chí đánh giá — đã xong, xem
> `docs/VA_WORKSPACE_OVERVIEW.md` §7, §21 và `Modules/Evaluation/README.md`
> nếu có). Tham khảo UI mẫu: màn hình "Danh sách mẫu đánh giá" +
> "Tạo mới mẫu đánh giá" của 1Office do người dùng cung cấp (ảnh chụp màn
> hình, không phải tài liệu chính thức — chỉ dùng để tham khảo bố cục).

## 1. Bối cảnh / mục tiêu

Tiêu chí đánh giá (Giai đoạn B) đã cho từng phòng ban tự tạo danh sách tiêu
chí (2 kiểu: thang điểm nhiều mức / cộng-trừ theo hành vi), gắn vào **loại
tiêu chí** (`evaluation_criterion_types`, ví dụ "Thái độ", "Kỹ năng, năng lực
chung"...). Giai đoạn C gộp các tiêu chí đó thành **Mẫu đánh giá** (evaluation
template) — một bộ tiêu chí có trọng số, dùng để tạo phiếu đánh giá nhân sự
sau này (Giai đoạn D, chưa nằm trong plan này).

Mục tiêu của giai đoạn này: **CRUD Mẫu đánh giá + Vị trí đánh giá + mẫu dùng
chung toàn hệ thống + Import/Export 3-tab + trường tùy biến**. Chưa làm phiếu
đánh giá thực tế, hội đồng, kỳ đánh giá (vẫn để ở Giai đoạn D như overview đã
ghi).

## 2. Quyết định phạm vi đã chốt với người yêu cầu (2026-08-26)

1. **Phạm vi tính năng**: làm đầy đủ như ảnh 1Office — không chỉ CRUD mẫu cơ
   bản, mà gồm cả Vị trí đánh giá, Import/Export, mã mẫu tự sinh, trường tùy
   biến. Khối lượng lớn → tách nhiều PR nhỏ theo §7 dưới đây, không làm 1
   lần.
2. **Sở hữu dữ liệu**: mặc định mẫu thuộc về 1 phòng ban (giống tiêu chí),
   nhưng **`department_director` trở lên** (department_director, deputy,
   admin, super_admin) có thể đánh dấu 1 mẫu là **`is_global = true`** —
   dùng chung cho **toàn bộ hệ thống** (mọi phòng ban thấy và dùng được),
   không phải chỉ chia sẻ nội bộ phòng ban đó.
3. **Vị trí UI — ĐỔI so với Giai đoạn B**: trang "Mẫu đánh giá" có **mục
   sidebar riêng**, KHÔNG phải tab trong `WorkspaceConfigHub`. Đây là điểm
   lệch có chủ đích so với nguyên tắc đã ghi ở Giai đoạn B ("không thêm mục
   sidebar riêng, chỉ tab trong hub") — cần cập nhật lại nguyên tắc đó trong
   `Modules/WorkspaceConfig/README.md` và overview §21 khi triển khai, nêu rõ
   lý do (mẫu đánh giá là entity độc lập, có thể dùng chung nhiều phòng ban,
   không còn "thuộc về" đúng 1 phòng ban như tiêu chí — không hợp để nhét
   trong Hub cấu hình phòng ban).
4. Tiêu chí đánh giá (Giai đoạn B) **không đổi vị trí** — vẫn là tab trong
   Hub. Chỉ Mẫu đánh giá (Giai đoạn C) có sidebar riêng.

## 3. Ngoài phạm vi (không làm trong Giai đoạn C)

- Phiếu đánh giá thực tế (chấm điểm nhân sự cụ thể theo mẫu), hội đồng nhiều
  vai trò, kỳ đánh giá (quý/tháng/năm) — Giai đoạn D.
- AI Support (mục thấy trên ảnh 1Office) — không nằm trong roadmap VA
  Workspace, bỏ qua.
- Đồng bộ "Vị trí đánh giá" với chức danh HRM thật — hiện `users`/`Department`
  còn stub HRM (xem `docs/known-issues.md`, memory `hrm-employee-sync-future`).
  Vị trí đánh giá ở giai đoạn này là danh mục tự quản trong Workspace, có
  trường tham chiếu HRM để nullable, giống mẫu `hrm_team_uuid` của `teams`.

## 4. Schema đề xuất

Bảng mới thuộc module `Evaluation` (`Modules/Evaluation/Database/migrations/`,
Laravel tự thêm prefix `va_workspace_`):

```
evaluation_positions                      -- "Vị trí đánh giá" (danh mục, dùng chung)
  id, name, description,
  hrm_position_uuid (nullable, đối chiếu HRM tương lai — không phải nguồn sự thật),
  created_by, created_at, updated_at

evaluation_templates
  id, department_id (nullable nếu is_global = true),
  code (mã mẫu tự sinh, ví dụ EVT-0025 — xem §5.1),
  name, description,
  is_global (bool — dùng chung toàn hệ thống, chỉ department_director+ được set true),
  is_active,
  created_by, updated_by, created_at, updated_at

evaluation_template_criteria             -- N-N template ↔ criteria, có trọng số riêng theo mẫu
  id, evaluation_template_id, evaluation_criteria_id,
  weight_label (enum: 'khong_quan_trong'|'quan_trong'|'kha_quan_trong'|'rat_quan_trong' — hiển thị
                chữ thường tiếng Việt, KHÔNG dùng số ẩn ý kỹ thuật ngoài UI, xem §6.4),
  weight_value (số dùng để tính điểm, map 1-1 từ weight_label),
  required_score (điểm yêu cầu tối thiểu, nullable),
  count_in_total (bool — "Tính vào tổng điểm"),
  sort_order

evaluation_template_positions            -- N-N template ↔ vị trí đánh giá
  id, evaluation_template_id, evaluation_position_id

evaluation_template_custom_fields        -- "Trường tùy biến" trên mẫu (§6.3)
  id, evaluation_template_id, label, field_type ('text'|'number'|'select'|'date'),
  options (json, nullable — cho field_type = select), sort_order, is_required
```

**Business rule — ĐÃ CHỐT (2026-08-26):**

- `evaluation_template_criteria.evaluation_criteria_id`:
  - Mẫu **thường** (`is_global = false`): chỉ được chọn tiêu chí có
    `is_active = true` và `department_id` trùng với
    `evaluation_templates.department_id` của chính mẫu đó.
  - Mẫu **global** (`is_global = true`): **được phép chọn tiêu chí từ bất kỳ
    phòng ban nào** (không giới hạn theo phòng ban người tạo mẫu) — chỉ cần
    `is_active = true`. UI chọn tiêu chí ở form mẫu global phải hiển thị kèm
    **tên phòng ban nguồn** của mỗi tiêu chí (vì danh sách sẽ trộn nhiều
    phòng ban, người dùng cần phân biệt được), xem §6.2.
  - `EvaluationTemplateService::attachCriteria()` (hoặc tương đương) phải
    validate lại rule trên ở tầng Service khi lưu — không chỉ chặn ở UI.
- Xoá `evaluation_criteria` đang được N-N trong `evaluation_template_criteria`
  → chặn xoá cứng, chỉ cho phép tắt `is_active` (giữ nguyên rule đã áp dụng ở
  Giai đoạn B nếu có, kiểm tra lại `EvaluationCriteriaService::destroy()`).
  Tắt `is_active` một tiêu chí đang dùng trong mẫu global (thuộc phòng ban
  khác) vẫn cho phép — mẫu chỉ ẩn tiêu chí đó khỏi lần tính điểm tiếp theo,
  không xoá liên kết N-N cũ (giữ lịch sử).
- `code` tự sinh tăng dần toàn hệ thống (không theo từng phòng ban), format
  **`EVT-{số 4 chữ số, zero-pad}`** — ví dụ `EVT-0001`, `EVT-0025`. Sinh trong
  `EvaluationTemplateService::store()` bằng `DB::transaction` + lock (tránh
  trùng số khi ghi đồng thời), không dùng `AUTO_INCREMENT` lộ trực tiếp ra
  UI (để sau này còn linh hoạt nếu cần đổi format).

## 5. Backend — Controller → Service → Repository

Theo đúng pattern bắt buộc (CLAUDE.md §5), mở rộng module `Evaluation` hiện
có (không tạo module mới):

```
App/Models/EvaluationTemplate.php
App/Models/EvaluationPosition.php
App/Http/Controllers/EvaluationTemplateController.php
App/Http/Controllers/EvaluationPositionController.php
App/Http/Requests/StoreEvaluationTemplateRequest.php
App/Http/Requests/UpdateEvaluationTemplateRequest.php
App/Repositories/Contracts/EvaluationTemplateRepositoryInterface.php
App/Repositories/Contracts/EvaluationPositionRepositoryInterface.php
App/Repositories/EvaluationTemplateRepository.php
App/Repositories/EvaluationPositionRepository.php
App/Services/EvaluationTemplateService.php
App/Services/EvaluationPositionService.php
```

Bind interface → implementation trong `EvaluationServiceProvider::register()`
(đã có sẵn cho `EvaluationCriteria`/`EvaluationCriterionType`, chỉ thêm 2 cặp
mới).

### 5.1 Route (`Modules/Evaluation/routes/manager.php`, prefix `/api/evaluation`)

```
GET    /evaluation/positions                  index (danh mục dùng chung)
POST   /evaluation/positions                  store   (department_director+)
PUT    /evaluation/positions/{id}
DELETE /evaluation/positions/{id}

GET    /evaluation/templates                  index — mặc định: mẫu của phòng ban user + mọi mẫu is_global
GET    /evaluation/templates/{id}
POST   /evaluation/templates                  store (evaluation.manage_department)
PUT    /evaluation/templates/{id}
PATCH  /evaluation/templates/{id}/toggle       bật/tắt is_active
PATCH  /evaluation/templates/{id}/toggle-global  chỉ department_director+ (permission riêng, xem §6.2)
DELETE /evaluation/templates/{id}
POST   /evaluation/templates/{id}/duplicate    nhân bản mẫu (tiện dụng khi tạo mẫu tương tự — xác nhận có cần ở §8)

GET    /evaluation/templates/data              modal Import/Export — 3 tab (§12 CLAUDE.md: import/export/reconcile)
POST   /evaluation/templates/data/import
GET    /evaluation/templates/data/export
```

Mã mẫu tự sinh: format `EVT-0001` (đã chốt §4) — xử lý trong
`EvaluationTemplateService::store()` bằng `DB::transaction` + lock để tránh
trùng số khi ghi đồng thời.

### 5.2 Quyền (mở rộng `config/permissions.php`)

- `evaluation.manage_department` (đã có) — tạo/sửa/xoá mẫu, vị trí đánh giá,
  trong phạm vi phòng ban mình.
- `evaluation.manage_global_template` (mới, reserved cho
  `department_director` trở lên: department_director, deputy, admin,
  super_admin) — bật `is_global`. **Không** cấp cho `section_head`/`team_lead`
  dù họ có `evaluation.manage_department` ở mức thấp hơn (nếu tương lai mở
  rộng ai được gán quyền này).
- super_admin: xem toàn bộ mẫu mọi phòng ban (đã có `workspace_config.view_all`
  pattern tương tự) — chỉ xem, không sửa thay, giữ đúng rule Giai đoạn B.

## 6. Frontend

### 6.1 Vị trí trong SPA — mục sidebar riêng (quyết định §2.3)

- Route mới trong `Modules/Evaluation/resources/js/router.js`:
  `/manager/evaluation-templates` (danh sách) — tên gọi UI tiếng Việt "Mẫu
  đánh giá" trên sidebar.
- Thêm mục sidebar trong cấu hình sidebar phòng ban
  (`resources/js/components/AppSidebar.vue` + cấu hình menu liên quan —
  kiểm tra cách `WorkspaceConfig`/`Evaluation` hiện đăng ký mục menu, dùng
  đúng cơ chế đó, KHÔNG tạo cơ chế đăng ký sidebar mới).
- Trang chi tiết mẫu (form tạo/sửa) dùng **form-modal** skill (lưới ngang
  2–3 cột, trong viewport, gần full-screen) — đúng mẫu vàng
  `WorkspaceConfigEvaluation.vue` hiện có cho tiêu chí.
- Trang danh sách dùng **data-table** skill (mẫu vàng `ActivityLog.vue`):
  cột kéo mép phải, ẩn scrollbar, panel chi tiết đẩy ngang khi click 1 dòng
  (xem UI đơn giản §14 CLAUDE.md — click 1 lần chỉ xem, không đổi gì).

### 6.2 Cột & filter danh sách (theo ảnh 1Office, việt hoá lại)

Không copy nguyên các cột kỹ thuật của 1Office. Áp dụng CLAUDE.md §14:

| Cột | Ghi chú |
|---|---|
| Tên mẫu đánh giá | |
| Số tiêu chí | đếm `evaluation_template_criteria` |
| Ngày tạo / Người tạo | avatar tip giống `UserAvatarTip.vue` đã có |
| Mã mẫu | |
| Tiêu chí đánh giá | preview rút gọn + "Xem thêm" — panel chi tiết khi click dòng, KHÔNG dùng tooltip hover (cấm §13). Với mẫu global, mỗi tiêu chí hiện kèm tên phòng ban nguồn (vì trộn nhiều phòng ban, xem §4) |
| Vị trí đánh giá | |
| Dùng chung toàn hệ thống | **KHÔNG dùng badge/pill nền màu** — chấm tròn nhỏ + chữ, giống `.perm-side__dot` (§14) |
| Trạng thái (hoạt động/tắt) | chấm tròn + chữ, không badge |

### 6.3 Trường tùy biến

Form tạo/sửa mẫu có khối "Trường tùy biến" — danh sách field động thêm/xoá
được (label + loại field), lưu vào `evaluation_template_custom_fields`. Mục
đích: chuẩn bị cho phiếu đánh giá Giai đoạn D có thể hiện thêm field ngoài
tiêu chí chuẩn (ví dụ "Nhận xét thêm của quản lý"), nhưng **Giai đoạn C chỉ
lưu định nghĩa field, chưa có UI nhập giá trị thật** (vì chưa có phiếu).

### 6.4 Trọng số hiển thị chữ, không phải số

Theo ảnh 1Office ("Khá quan trọng", "Quan trọng", "Rất quan trọng") — đúng
tinh thần CLAUDE.md §14 (câu tiếng Việt phổ thông, không lộ số/kỹ thuật ra
UI). Dropdown chọn trọng số hiển thị nhãn chữ; `weight_value` số ẩn phía sau
để tính điểm.

## 7. Thứ tự triển khai đề xuất (tách PR nhỏ)

1. **[XONG] PR1 — Schema + Backend CRUD Mẫu đánh giá cơ bản**: migration
   `evaluation_templates` + `evaluation_template_criteria`, Model/Repository/
   Service/Controller/Request, route, quyền `evaluation.manage_global_template`.
   Backend `toggleGlobal()` + route `toggle-global` cũng đã có sẵn từ PR1
   (cùng Controller với CRUD) — nhưng UI bật/tắt dùng chung vẫn để ở PR4,
   chưa có trong PR2. Chưa có Vị trí đánh giá, chưa Import/Export, chưa
   trường tùy biến.
2. **[XONG] PR2 — Frontend danh sách + form mẫu**: trang sidebar riêng
   (`/manager/evaluation-templates`, mục "Mẫu đánh giá" trong
   `resources/js/components/AppSidebar.vue`, chỉ department_director/deputy
   trở lên qua `requiresPermission: evaluation.manage_department` — route
   guard tổng quát mới thêm vào `resources/js/router/index.js`). Data-table +
   form-modal theo đúng 2 skill; CRUD đầy đủ (tạo/sửa/xoá/toggle active/
   duplicate) + chọn nhiều tiêu chí kèm trọng số hiển thị chữ; field ngay
   hàng ở panel chi tiết, chấm màu thay badge (CLAUDE.md §14). Chưa có UI
   cho: Vị trí đánh giá, toggle dùng chung toàn hệ thống, trường tùy biến,
   Import/Export.
3. **[XONG] PR3 — Vị trí đánh giá**: migration `evaluation_positions` +
   `evaluation_template_positions` (N-N, `evaluation_positions.name` unique
   toàn hệ thống). Backend: `EvaluationPosition` Model, Repository/Service/
   Controller/Request riêng, route `/api/evaluation/positions` (CRUD, quyền
   `evaluation.manage_department` — vị trí là danh mục dùng chung, không
   scoped theo phòng ban). `EvaluationTemplateService` mở rộng
   `create()`/`update()`/`duplicate()` để đồng bộ `position_ids` (validate
   id tồn tại trước khi `syncPositions()`), `present()` trả thêm
   `positions`. Frontend: cột "Vị trí đánh giá" trong bảng + panel chi tiết,
   field chọn nhiều vị trí kiểu chip trong form (`.evtpl-position-picker`),
   dialog nhỏ "Thêm vị trí đánh giá" ngay trong form mẫu (không cần rời
   trang). Đã verify qua smoke test Service (transaction rollback) + `vite
   build`.
4. **[XONG] PR4 — UI Mẫu dùng chung toàn hệ thống (`is_global`)**: cột +
   chấm trạng thái "Dùng chung toàn hệ thống" trên bảng và panel chi tiết;
   nút hành động rõ ràng "Đánh dấu dùng chung" / "Bỏ dùng chung" trong panel
   chi tiết (không phải click đơn trên dòng bảng, đúng §14), chỉ hiện với
   `evaluation.manage_global_template`. Backend mới: endpoint
   `GET /api/evaluation/templates/global-criteria-pool` (đặt trước
   `/templates/{id}` trong route để không bị nuốt mất) +
   `EvaluationTemplateService::listCriteriaAcrossDepartments()` +
   `EvaluationCriteriaRepositoryInterface::allActiveAcrossDepartments()` mới
   — trả tiêu chí active mọi phòng ban kèm tên phòng ban, chỉ cho
   `evaluation.manage_global_template`. Frontend: form chọn tiêu chí tự
   chuyển nguồn (cùng phòng ban / toàn hệ thống) theo `template.is_global`,
   picker hiện tên phòng ban nguồn khi trộn nhiều phòng ban. Đã verify qua
   smoke test Service (transaction rollback, xác nhận mẫu thường bị chặn
   chọn tiêu chí phòng ban khác còn mẫu global thì được) + `vite build` +
   kiểm tra thứ tự route.
5. **[XONG] PR5 — Trường tùy biến**: migration
   `evaluation_template_custom_fields` (label, field_type
   text/number/select/date, options JSON, is_required, sort_order). Backend:
   `EvaluationTemplateCustomField` Model, `syncCustomFields()` trong
   Repository, `buildCustomFieldRows()` validate trong Service (label bắt
   buộc, field_type hợp lệ, select phải có ≥1 option), tích hợp vào
   `create()`/`update()`/`duplicate()`/`present()`. Frontend: khối "Trường
   tùy biến" trong form (thêm/xoá field, thêm/xoá option khi field_type =
   select), hiển thị trong panel chi tiết CHỈ KHI có dữ liệu thật (§14 —
   không giữ chỗ rỗng). PR5 chỉ lưu định nghĩa field, KHÔNG có UI nhập giá
   trị thật (chờ phiếu đánh giá Giai đoạn D). Đã verify qua smoke test
   Service (transaction rollback) + `vite build`.
6. **[XONG] PR6 — CHỈ Export Excel (đã chốt lại phạm vi 2026-08-26)**: lib
   `phpoffice/phpspreadsheet` đã có sẵn trong `composer.json` (dùng chung
   với `EvaluationCriteriaExcelExporter` — không cần chọn lib mới, huỷ bỏ
   nghi vấn ở overview §20.10 cho riêng module Evaluation). **KHÔNG làm
   Import cho Mẫu đánh giá** — khác tiêu chí đánh giá (phẳng, 1 dòng = 1 bản
   ghi), mẫu có cấu trúc lồng nhau (N-N tiêu chí kèm trọng số riêng, N-N vị
   trí, trường tùy biến JSON) không phẳng được an toàn thành 1 dòng Excel;
   rủi ro sai lệch khi nhập lại cao hơn lợi ích ở giai đoạn này. Không phải
   3-tab import/export/reconcile như CLAUDE.md §12 mô tả cho trường hợp
   tổng quát — đây là ngoại lệ có chủ đích, chỉ 1 nút "Xuất Excel" trên
   toolbar danh sách. Backend: `EvaluationTemplateExcelExporter` (cùng
   khuôn mẫu `EvaluationCriteriaExcelExporter`), `ExportEvaluationTemplateRequest`,
   `EvaluationTemplateService::export()` (gộp tiêu chí/vị trí/trường tùy
   biến thành text mô tả trong 1 dòng — không tách bảng con), route
   `GET /templates/export` (đặt trước `/templates/{id}`). Frontend: nút
   "Xuất Excel" trong `TablePagesBar` slot `#actions`. Đã verify qua smoke
   test Service (file thật sinh ra, kích thước > 0, filter q hoạt động,
   transaction rollback) + `vite build` + kiểm tra thứ tự route.

Không bắt buộc theo đúng thứ tự tuyệt đối, nhưng PR1+PR2 phải xong trước mọi
PR sau (nền tảng CRUD) — cả hai đã xong. **Toàn bộ PR1–PR6 đã hoàn tất** —
Evaluation Giai đoạn C (Mẫu đánh giá) coi như xong theo phạm vi đã chốt.

## 8. Điểm đã chốt (2026-08-26) / rủi ro còn mở

Đã chốt với người yêu cầu, không hỏi lại khi implement:

1. **Format mã mẫu tự sinh** — `EVT-0001`, `EVT-0002`... (tiền tố `EVT-` +
   4 chữ số zero-pad). Xem §4, §5.1.
2. **Mẫu global được chọn tiêu chí từ bất kỳ phòng ban nào** (không giới hạn
   theo phòng ban người tạo mẫu). UI phải hiện tên phòng ban nguồn kèm mỗi
   tiêu chí trong danh sách chọn — xem §4, §6.2.
3. **Nhân bản mẫu (`duplicate`)** — làm trong Giai đoạn C, dùng đề xuất mặc
   định (route `POST /evaluation/templates/{id}/duplicate`, xem §5.1).
4. **Vị trí đánh giá là N-N** (1 mẫu áp dụng nhiều vị trí) — dùng đề xuất mặc
   định `evaluation_template_positions`, xem §4, §8.4 cũ.
5. **`section_head` KHÔNG vào trang Mẫu đánh giá** — giữ đúng phạm vi như Hub
   cũ (chỉ `department_director`/`deputy_department_director` trở lên), dù
   trang đã tách sidebar riêng. Áp dụng cho route middleware + điều kiện hiện
   mục sidebar.

Còn mở, cần chốt trước PR tương ứng:

1. **Import/Export** dùng lib Excel nào — vẫn là quyết định mở ở overview
   §20.10, chặn PR6 tới khi chốt.

## 9. Cập nhật tài liệu khi triển khai

- `docs/VA_WORKSPACE_OVERVIEW.md` — cập nhật §21 (Bước 2 đã chọn: Evaluation
  Giai đoạn C), thêm dòng "Đã chốt" mới về sidebar riêng cho Mẫu đánh giá
  (khác Giai đoạn B), cập nhật bảng lộ trình §19 (Phase 1c mở rộng hoặc
  thêm Phase 1e).
- `Modules/WorkspaceConfig/README.md` — làm rõ nguyên tắc "không thêm mục
  sidebar riêng" chỉ áp dụng cho tab trong Hub (Thành viên/Menu/Tiêu chí),
  không áp dụng cho Mẫu đánh giá.
- Tạo `docs/modules/Evaluation.md` theo `docs/README.md` khi PR1 xong (chưa
  có file này — kiểm tra lại, tạo mới nếu thiếu).
