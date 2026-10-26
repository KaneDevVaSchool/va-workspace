# Đơn từ — Detailed design & implementation plan

> **Trạng thái: ĐỀ XUẤT (2026-10-26)** — chưa code. Khi triển khai xong một
> phần, ghi trạng thái ở dòng này (phần nào xong / phần nào chưa). Không sửa
> lại nội dung gốc để “khớp” code sau này.
>
> Nguồn nghiệp vụ: *Ma trận trạng thái Ticket V3* (baseline vòng đời) và
> *Phân tích chức năng tổng quan V2* (cây module). Sản phẩm trên
> workspace.vaschools.edu.vn gọi là **Đơn từ** / **Hồ sơ đơn từ**. Không dùng
> chữ “ticket” trên UI, permission label, menu, hay tài liệu người dùng.

## 0. Kiểm kê và checklist hoàn thành

Tick `- [x]` ngay trên file này khi hạng mục đạt tiêu chí một dòng kế bên.
Chưa code. P0 xong vẫn **0/29** hạng mục nghiệp vụ — P0 chỉ là khung.

### 0.1 Ba con số

| Câu hỏi | Số | Ý nghĩa |
|---|---:|---|
| Module phần mềm cần dựng | **1** | `Modules/DonTu`. Một mục sidebar 「Đơn từ」. Không tách 6 repo/module Laravel. |
| Phân hệ nghiệp vụ (spec gọi là module) | **6** | Nằm trong DonTu. Checklist §0.2. |
| Hạng mục chức năng | **29** | Đủ mã F-* của spec V2. Checklist §0.3. |
| Giao diện người dùng | **24** | **15 màn** (route) + **9 hộp thoại**. Checklist §0.4. |

Một màn chi tiết hồ sơ có nhiều tab; tab không tính thêm màn. Hộp thoại không có route riêng.

| Đợt | Hạng mục xong hẳn | Màn mới | Hộp thoại mới |
|---|---:|---:|---:|
| P0 khung | 0 | 1 (UI-11 đọc) | 0 |
| P1 vận hành nội bộ | 6 | 5 | 5 |
| P2 cấu hình + tuần tự + tiền | 12 | 5 | 4 |
| P3 phụ huynh + giám sát + ký số | 11 | 4 | 0 |
| **Cộng** | **29** | **15** | **9** |

### 0.2 Sáu phân hệ

Tick phân hệ khi mọi hạng mục con ở §0.3 đã tick.

- [ ] **M1 — Biểu mẫu động.** 5 hạng mục. Xong ở đợt P2 (4) và P3 (1).
- [ ] **M2 — Quy trình.** 5 hạng mục. Xong ở đợt P2.
- [ ] **M3 — Nộp và theo dõi.** 4 hạng mục. Xong ở đợt P1.
- [ ] **M4 — Hộp thư duyệt.** 6 hạng mục. Đợt P1 xong 2, P2 xong 3, P3 xong 1.
- [ ] **M5 — Đồng bộ phụ huynh.** 5 hạng mục. Xong ở đợt P3.
- [ ] **M6 — Giám sát và kiểm toán.** 4 hạng mục. Xong ở đợt P3.

### 0.3 Hai mươi chín hạng mục

Tiêu chí tick: đúng một dòng DoD, có test hoặc thao tác được trên UI của đợt đó. Làm dở không tick.

#### Biểu mẫu động

- [ ] **F-TPL-01** Danh mục mẫu biểu. **P2.** UI-07: thêm, sửa, nhân bản, ẩn/hiện, version, scope internal/external/both. DoD: mẫu đang gắn quy trình active không xóa cứng được.
- [ ] **F-TPL-02** Thêm và sắp trường. **P2.** UI-08 + D6. DoD: kéo thứ tự, mã trường trùng trong một mẫu bị từ chối.
- [ ] **F-TPL-03** Kiểu trường. **P2.** Text, textarea, checkbox, radio, select, ngày, file. DoD: soạn đơn P1 render đúng kiểu đã lưu trên schema.
- [ ] **F-TPL-04** Tiền tệ và ô ký. **P2.** DoD: số tiền lưu decimal, không âm; ô ký bắt buộc có câu cam kết.
- [ ] **F-TPL-05** Nhận diện và in. **P3.** DoD: xem trước A4 có logo, header, watermark; PDF xuất từ hồ sơ đã hoàn thành.

#### Quy trình

- [ ] **F-WFL-01** Danh mục quy trình. **P2.** UI-09. DoD: bật/tắt `is_active`; người không thuộc đối tượng khởi tạo không thấy quy trình ở UI-01.
- [ ] **F-WFL-02** Bộ trạng thái. **P2** (seed mã V3 làm ở P0, chưa tính xong). UI-11 sửa. DoD: quy trình gắn một `status_set_code`; không seed bộ mặc định bằng mã V2 `SUBMITTED_ASSESSMENT`.
- [ ] **F-WFL-03** Nhiều phiếu một hồ sơ. **P2.** DoD: phiếu 2 nằm cùng `reference_code`, không tạo hồ sơ con.
- [ ] **F-WFL-04** Ma trận duyệt. **P2.** UI-10 + D7. DoD: mỗi bước có `status_group_code` và `target_form_keys`; đổi bước không tự ý đổi trạng thái nếu cùng mã.
- [ ] **F-WFL-05** Cột mốc, đóng hồ sơ, task. **P2.** DoD: hết bước chuyên môn luôn sang Chờ xác nhận hoàn tất; bước `workspace_task` tạo task Project.

#### Nộp và theo dõi — cả bốn xong ở P1

- [ ] **F-REQ-01** Cổng nộp đơn. UI-01 → UI-02. DoD: chỉ hiện quy trình active mà user được khởi tạo.
- [ ] **F-REQ-02** Hồ sơ của tôi. UI-03 + UI-04. DoD: stepper hiện trạng thái vĩ mô; mở chi tiết mới thấy bước đang giữ.
- [ ] **F-REQ-03** Nháp và hủy. D5 + D2. DoD: nháp không có mã HS- và không notify; hủy chỉ trước bước đóng.
- [ ] **F-REQ-04** Trao đổi trên hồ sơ. Tab ý kiến của UI-04. DoD: hồ sơ Hoàn thành / Từ chối / Hủy không thêm bình luận.

#### Hộp thư duyệt

- [ ] **F-APR-01** Hộp thư. **P1.** UI-05. DoD: chỉ hồ sơ user đang là assignee `pending`; badge sidebar bằng `inbox/count`.
- [ ] **F-APR-02** Chi tiết nhiều tab. **P2** mới tick (P1 có 3 tab: Phiếu, Ý kiến, Lịch sử). DoD: thêm tab Phiếu tuần tự và tab Tác vụ.
- [ ] **F-APR-03** Duyệt, từ chối, yêu cầu bổ sung. **P1.** D1 + D3. DoD: từ chối dưới 10 ký tự trả 422; yêu cầu bổ sung không đổi mã hồ sơ.
- [ ] **F-APR-04** Ký đa hình thức. **P3** mới tick (P1 chỉ ký online). DoD: online, SmartCA, offline (upload scan) cùng ghi `sign_mode` trên audit.
- [ ] **F-APR-05** Thực thi sau duyệt. **P2.** D9. DoD: kế toán nhập UNC rồi trạng thái sang Chờ xác nhận hoàn tất.
- [ ] **F-APR-06** Ủy quyền và snapshot. **P2.** D8. DoD: người được ủy quyền duyệt thay; audit ghi cả người gốc và người ký.

#### Đồng bộ phụ huynh — cả năm xong ở P3

- [ ] **F-EXT-01** Xuất danh mục và schema form sang app. DoD: app nhận được quy trình `initiator=parent|both`.
- [ ] **F-EXT-02** Nhận đơn inbound. DoD: một idempotency key không tạo hai hồ sơ.
- [ ] **F-EXT-03** Ký phiếu tuần tự hai chiều. DoD: chặng phiếu 2 đẩy được sang app và nhận chữ ký về cùng mã hồ sơ.
- [ ] **F-EXT-04** Callback trạng thái và comment. DoD: mỗi chuyển trạng thái macro phát một sự kiện, nhãn tiếng Việt.
- [ ] **F-EXT-05** Hàng đợi. DoD: lỗi gọi app vào hàng đợi thử lại, không làm fail transaction duyệt.

#### Giám sát — cả bốn xong ở P3

- [ ] **F-MON-01** Dashboard tiến độ. UI-12. DoD: đếm theo trạng thái vĩ mô, lọc được phòng ban.
- [ ] **F-MON-02** SLA. UI-13. DoD: quá hạn tính từ `sla_hours` của bước, không từ cả hồ sơ.
- [ ] **F-MON-03** Báo cáo theo cơ sở và loại đơn. UI-14. DoD: xuất được danh sách hồ sơ đúng bộ lọc, không sửa dữ liệu.
- [ ] **F-MON-04** Nhật ký toàn hệ thống. UI-15. DoD: không có API sửa/xóa `don_tu_status_audits`. P1 đã ghi log và hiện trên UI-04; tick hạng mục này khi có UI-15.

### 0.4 Hai mươi bốn giao diện

#### 15 màn

- [ ] **UI-01** Nộp đơn. **P1.** `/manager/don-tu`. Lưới thẻ quy trình. Người nộp.
- [ ] **UI-02** Soạn đơn. **P1.** `/manager/don-tu/new/:workflowId` và nháp `/manager/don-tu/submissions/:id/edit`. Cùng một màn. Lưu nháp / Gửi.
- [ ] **UI-03** Hồ sơ của tôi. **P1.** `/manager/don-tu/mine`. Bảng mẫu ActivityLog, panel 28rem.
- [ ] **UI-04** Chi tiết hồ sơ. **P1.** `/manager/don-tu/submissions/:id`. Stepper vĩ mô. Tab P1: Phiếu, Ý kiến, Lịch sử. Tab P2 thêm trên cùng màn, không route mới.
- [ ] **UI-05** Cần tôi duyệt. **P1.** `/manager/don-tu/inbox`. Cùng khung bảng UI-03.
- [ ] **UI-06** Tra cứu. **P2.** `/manager/don-tu/registry`. Trưởng phòng, giám đốc, admin.
- [ ] **UI-07** Danh mục mẫu biểu. **P2.** `/manager/don-tu/admin/forms`.
- [ ] **UI-08** Cấu hình mẫu biểu. **P2.** `/manager/don-tu/admin/forms/:id`. Tab thông tin, trường, in (in gắn P3).
- [ ] **UI-09** Danh mục quy trình. **P2.** `/manager/don-tu/admin/workflows`.
- [ ] **UI-10** Cấu hình quy trình. **P2.** `/manager/don-tu/admin/workflows/:id`. Phiếu gắn, ma trận bước, đóng hồ sơ, task.
- [ ] **UI-11** Bộ trạng thái. **P0** đọc tại `/manager/don-tu/admin`. **P2** mới tick khi sửa được bộ tùy biến.
- [ ] **UI-12** Giám sát tiến độ. **P3.** `/manager/don-tu/monitor`.
- [ ] **UI-13** Báo cáo SLA. **P3.** `/manager/don-tu/monitor/sla`.
- [ ] **UI-14** Báo cáo nghiệp vụ. **P3.** `/manager/don-tu/monitor/reports`.
- [ ] **UI-15** Nhật ký kiểm toán. **P3.** `/manager/don-tu/monitor/audits`.

#### 9 hộp thoại

- [ ] **D1** Từ chối. **P1.** Lý do bắt buộc, tối thiểu 10 ký tự. Dialog nhỏ.
- [ ] **D2** Hủy hồ sơ. **P1.** Lý do bắt buộc. Dialog nhỏ.
- [ ] **D3** Yêu cầu bổ sung. **P1.** Ghi mục cần sửa. Dialog nhỏ.
- [ ] **D4** Xác nhận đóng. **P1.** Cam kết nghiệm thu. Dialog nhỏ.
- [ ] **D5** Xóa nháp. **P1.** Confirm. Chỉ khi DRAFT.
- [ ] **D6** Thêm hoặc sửa trường. **P2.** Form modal 2–3 cột.
- [ ] **D7** Thêm hoặc sửa bước duyệt. **P2.** Form modal 2–3 cột.
- [ ] **D8** Ủy quyền. **P2.** Chọn người nhận, khoảng ngày.
- [ ] **D9** Xác nhận đã chi. **P2.** Mã UNC và file ủy nhiệm chi.

#### Ai thấy gì

| Vai | Màn | Hộp thoại |
|---|---|---|
| Người nộp | UI-01, UI-02, UI-03, UI-04 | D2, D4, D5 |
| Người duyệt | UI-05, UI-04 | D1, D3; P2 thêm D8, D9 nếu là bước của họ |
| Trưởng phòng / BGH tra cứu | UI-06, UI-04 (chỉ xem) | không |
| Quản trị phòng | UI-07 đến UI-11 | D6, D7 |
| BGH / admin giám sát | UI-12 đến UI-15 | không |

P1 giao cho người dùng: **6 màn** (UI-01–05 và UI-11 chỉ đọc) và **5 hộp thoại** (D1–D5).

### 0.5 Khung P0 — không tính vào 29

- [ ] Module `DonTu` bật trong `modules_statuses.json`, route `/api` và router Vue.
- [ ] Quyền `don_tu.*` trong `config/permissions.php`; `don_tu.manage_system` reserved.
- [ ] Mục sidebar Đơn từ, nhóm Điều hành, bốn catalog menu.
- [ ] Migration master §7 + seed 9 trạng thái và cạnh T01–T13.
- [ ] `GET /api/don-tu/status-sets` trả `PENDING_HANDLING` / 「Chờ xử lý」.
- [ ] UI-11 đọc được bộ mặc định. Các màn P1 còn lại được route, chưa cần nghiệp vụ.

**Xong P0:** trưởng phòng thấy menu, mở hub, API trả đúng nhãn. Hạng mục §0.3 vẫn 0/29.

## 1. Bối cảnh / mục tiêu

Workspace đã có Identity, phân quyền, sidebar theo phòng ban, Project/Task,
thông báo, ActivityLog. Chưa có nơi CBNV nộp đơn hành chính và lãnh đạo duyệt
theo quy trình cấu hình được.

Mục tiêu: thêm **một mục sidebar 「Đơn từ」** — hub khởi tạo, theo dõi, duyệt
và (theo phase) cấu hình biểu mẫu + quy trình. Mỗi lần nộp đơn tạo **một hồ
sơ** (process instance), không phải phiếu rời. Trạng thái hiển thị là **tầng
vĩ mô**; bước duyệt là **tầng vi mô** nằm trong trạng thái đó.

Triết lý (từ spec, giữ nguyên):

1. Nộp đơn = khởi tạo một quy trình mẫu từ đầu đến cuối.
2. Hiển thị trạng thái vĩ mô trước, bước vi mô khi mở chi tiết.
3. Bộ trạng thái là master data, gán vào quy trình lúc cấu hình
   (`workflow.status_set_code`).
4. Nhiều phiếu trên **cùng một mã hồ sơ** (đơn tuần tự), không tạo đơn con.
5. Hộp thư duyệt là một điểm đến; bước có cờ task thì đồng bộ sang Công việc.

## 2. Quyết định thiết kế đề xuất

Các mục dưới đây là mặc định để triển khai được. Mục 14 liệt kê điểm còn cần
người yêu cầu xác nhận; nếu không phản hồi, làm theo mặc định này.

| # | Quyết định | Lý do |
|---|---|---|
| D1 | Module Laravel/Vue mới `Modules/DonTu`, alias `don-tu` | Cùng mẫu `Report` / `FeatureRequest`: `module.json`, ServiceProvider, route `/api` middleware `web`, router Vue gộp vào `resources/js/router/index.js`. |
| D2 | Permission namespace `don_tu.*`, **không** tái dùng `process.*` | `process.manage_department` đã nằm trong matrix role nhưng chưa có màn hình. Gộp vào key cũ sẽ cấp nhầm quyền “quy trình” cũ cho trưởng phòng trước khi module tồn tại. `process.*` giữ nguyên, không active. |
| D3 | Một mục sidebar **Đơn từ**, nhóm **Điều hành**, `configurableByDepartment: true` | Cùng nhóm Dự án / Công việc / Báo cáo. Không tách 6 module spec thành 6 mục menu. |
| D4 | Không đổi route `/` (home, nhãn hiện tại 「Quy trình」) ở P0–P1 | Trang home đang là placeholder. Gộp hoặc redirect sang Đơn từ là quyết định sản phẩm riêng, làm sau khi hub chạy. |
| D5 | Mã trạng thái DB/API = bộ **Matrix V3** (`STATUS_SET_DEFAULT`) | V3 là spec baseline. Sơ đồ V2 (`SUBMITTED_ASSESSMENT`, `LEADERSHIP_APPROVAL`…) là cách nhóm bước cũ, **không** seed làm mã mặc định. Bước duyệt gắn vào mã V3 qua `status_group_code`. |
| D6 | Nhãn UI tiếng Việt; mã kỹ thuật SNAKE_CASE tiếng Anh | Ví dụ API `PENDING_APPROVAL`, badge 「Chờ duyệt」. |
| D7 | P1 ký **online 1-click** (đồng ý + timestamp + user). SmartCA và ký offline để phase sau | Đủ để duyệt nội bộ. Không chặn P1 vì tích hợp chứng thư số. |
| D8 | Phạm vi hồ sơ theo `department_id` của người nộp, lọc giống Report/Contract | Trưởng phòng thấy hồ sơ phòng mình; member thấy hồ sơ mình tạo hoặc được giao duyệt. |
| D9 | Pilot P1: **một quy trình nội bộ đơn giản** trên `STATUS_SET_DEFAULT`, không đi tiền, không phiếu 2, không App Phụ huynh | Cắt vòng đời T02 → T03 → T04/T09 → T10, cộng T11/T12/T01. Mua sắm, hoàn phí, phụ huynh là P2–P3. |
| D10 | Bảng prefix `va_workspace_` do connection `mysql` (`DB_PREFIX`). Migration ghi tên logic không prefix | Giống mọi module hiện có. |

### Đối chiếu hai tài liệu trạng thái

| Nguồn | Dùng thế nào |
|---|---|
| Matrix V3 §II, §IV, §V | **Luật runtime** của `STATUS_SET_DEFAULT`: mã, chuyển T01–T13, RBAC theo trạng thái. |
| V2 §4.1, §4.4 | **Cách hiển thị và gắn bước**: macro status là container; `macro_status = current_step.status_group_code`. |
| V2 §4.3 (`STATUS_SET_HR` mã riêng `MANAGER_REVIEW`…) | Catalog **mở rộng**, seed ở P2 dưới dạng bộ tùy chọn. Không thay bộ mặc định. |
| Matrix V3 §7 (`STATUS_SET_PROCUREMENT` / `HR` tái dùng mã `PENDING_*`) | Cùng engine, khác **nhãn**. Ưu tiên cách này khi seed P2: cùng mã, nhãn chuyên biệt, thêm trạng thái chỉ khi nghiệp vụ thật sự cần mã mới. |

## 3. Phạm vi

### Trong P0 — khung module (chưa vận hành đơn thật)

- Scaffold `DonTu`, bật `modules_statuses.json`.
- Quyền catalog + gán role (mục 9).
- Mục sidebar + 5 route hub (có thể trống dữ liệu).
- Migration master data + seed `STATUS_SET_DEFAULT` và transition T01–T13.
- API đọc bộ trạng thái (admin).

### Trong P1 — vận hành nội bộ tối thiểu

- 1 mẫu biểu (form schema JSON, field text/textarea/select/date/file).
- 1 quy trình mẫu: Tiếp nhận → Duyệt (1–n cấp, cùng `PENDING_APPROVAL`) → Xác nhận đóng → Hoàn thành.
- Cổng nộp đơn, lưu nháp, gửi, hủy (trước bước đóng).
- Hồ sơ của tôi + hộp thư 「Cần tôi duyệt」 + chi tiết (stepper vĩ mô, tab phiếu, ý kiến, lịch sử).
- Hành động: tiếp nhận, phê duyệt, từ chối (lý do ≥ 10 ký tự), yêu cầu bổ sung (cờ revise, không đổi mã hồ sơ), xác nhận đóng.
- Audit chuyển trạng thái bất biến.
- Thông báo in-app khi sang lượt / bị từ chối / bị hủy.

### Trong P2

- Builder mẫu biểu (field tiền tệ, ô ký, ẩn/hiện có điều kiện, version).
- Builder quy trình: gán bộ trạng thái, ma trận bước, SLA, `target_form_keys`.
- Đơn tuần tự (`PENDING_SUPPLEMENTAL_FORM`, pre-fill phiếu 2).
- Nhánh tiền (`PENDING_PAYMENT`, UNC, file ủy nhiệm chi) — nhập liệu, chưa đối soát sổ cái.
- Bộ `STATUS_SET_PROCUREMENT`, `STATUS_SET_HR`, `STATUS_SET_FAST_TRACK`.
- Đồng bộ task Project khi bước bật `workspace_task`.
- Tra cứu hồ sơ cấp phòng / BGH.
- Ủy quyền duyệt (snapshot người được ủy quyền tại thời điểm ký).

### Trong P3

- App Phụ huynh: outbound schema, inbound nộp đơn, callback trạng thái, idempotency, queue.
- Dashboard SLA, báo cáo theo cơ sở / loại đơn.
- Ký SmartCA và ký offline (upload scan).
- Watermark / PDF pháp lý / header văn bản hành chính.
- Override khẩn cấp của admin hệ thống.

### Ngoài phạm vi (không thiết kế chi tiết ở file này)

- Sổ cái kế toán, quyết toán học phí thật.
- Thay thế trang home 「Quy trình」.
- Workflow BPMN đồ họa kéo-thả. Ma trận bước là bảng có thứ tự, không phải canvas BPMN.

## 4. Thuật ngữ

| Spec | UI | Mã |
|---|---|---|
| Ticket / process instance | Hồ sơ đơn từ | `don_tu_submissions` |
| Mã `HS-…` | Mã hồ sơ | `reference_code` |
| Form template | Mẫu biểu | `don_tu_form_templates` |
| Workflow | Quy trình mẫu | `don_tu_workflows` |
| Status set | Bộ trạng thái | `don_tu_status_sets` |
| Macro status | Trạng thái | `macro_status_code` |
| Approval step | Bước duyệt | `don_tu_workflow_steps` |
| Inbox | Cần tôi duyệt | route `inbox` |

`reference_code` sinh lúc **gửi** (T02), không sinh lúc nháp. Format đề xuất:
`HS-{campus_or_dept}-{YYYY}-{seq4}` (ví dụ `HS-HCNS-2026-0033`). Sequence theo
năm + tiền tố, unique toàn hệ thống.

## 5. Vị trí trong Workspace

### Sidebar

| Thuộc tính | Giá trị |
|---|---|
| Nhóm | `operations` — Điều hành |
| `menu_key` / route name | `manager.don-tu.hub` |
| Nhãn | Đơn từ |
| Icon | `fileText` (đã có trong `appIconNames.js`) |
| Path | `/manager/don-tu` |
| Hiện khi | `don_tu.submit` **hoặc** `don_tu.approve` **hoặc** `don_tu.view_department` **hoặc** `don_tu.manage_department` **hoặc** `don_tu.manage_system` |
| Cấu hình phòng ban | `configurableByDepartment: true` |

Đồng bộ thủ công 4 chỗ (cùng quy ước menu hiện tại):

- `resources/js/components/AppSidebar.vue` — `MENU_SECTIONS.operations`
- `Modules/WorkspaceConfig/App/Services/DepartmentSidebarConfigService.php` — `CONFIGURABLE_MENUS` + `MENU_DEFAULT_SECTIONS`
- `Modules/WorkspaceConfig/App/Services/GlobalMenuVisibilityService.php` — `CATALOG`
- `Modules/WorkspaceConfig/resources/js/constants/sidebarMenus.js` — `SIDEBAR_MENU_CATALOG`

Badge số hồ sơ đang chờ user: poll `GET /api/don-tu/inbox/count`, key
`manager.don-tu.hub`, cùng nhịp badge Duyệt bài / Duyệt bảng công trong
`AppSidebar.vue`.

### Hub (sub-nav trong trang, không thêm mục sidebar)

| Tab | Path | Route name | Quyền vào tab |
|---|---|---|---|
| Nộp đơn | `/manager/don-tu` | `manager.don-tu.hub` | `don_tu.submit` |
| Hồ sơ của tôi | `/manager/don-tu/mine` | `manager.don-tu.mine` | `don_tu.submit` |
| Cần tôi duyệt | `/manager/don-tu/inbox` | `manager.don-tu.inbox` | `don_tu.approve` |
| Tra cứu | `/manager/don-tu/registry` | `manager.don-tu.registry` | `don_tu.view_department` |
| Cấu hình | `/manager/don-tu/admin` | `manager.don-tu.admin` | `don_tu.manage_department` hoặc `don_tu.manage_system` |

`/manager/don-tu` là lối sidebar. User chỉ có quyền duyệt (không có `submit`)
vào hub thì redirect sang `inbox`.

Cấu hình P1 chỉ đọc bộ trạng thái + bật/tắt quy trình pilot. Builder đầy đủ
là P2, vẫn nằm trong tab Cấu hình.

## 6. Kiến trúc

```
Vue hub (DonTuLayout)
  → axios /api/don-tu/*
    → DonTu*Controller
      → DonTuSubmissionService | DonTuWorkflowService | DonTuStatusService
        → *Repository
        → StatusTransitionService   (luật T01–T13, không nằm trong controller)
```

Ràng buộc giống Report:

- Controller không query Eloquent chéo module.
- Gọi Identity qua repository/service đã có (`User`, phòng ban, notification).
- P2 gọi Project qua service tạo task, không ghi thẳng bảng `tasks`.

### Lớp bắt buộc

| Lớp | Trách nhiệm |
|---|---|
| `StatusCatalog` | Đọc bộ trạng thái, nhãn, thứ tự, loại (main / conditional / terminal). |
| `StatusTransitionService` | `assertAllowed(from, action, actor, submission)` rồi `apply`. Ghi audit trong cùng transaction. |
| `WorkflowRuntime` | Biết bước hiện tại, bước kế, người được giao, `status_group_code` của bước. |
| `FormSchemaValidator` | Validate payload theo schema phiên bản đã chốt trên hồ sơ. |
| `ReferenceCodeGenerator` | Sinh `HS-…` đúng một lần lúc gửi. |

Mọi đổi `macro_status_code` đi qua `StatusTransitionService`. Không `update`
trạng thái rải ở controller.

### Vòng đời một hồ sơ P1

```
[không có] --lưu nháp--> DRAFT
DRAFT --gửi--> PENDING_HANDLING
PENDING_HANDLING --tiếp nhận--> PENDING_APPROVAL
PENDING_APPROVAL --duyệt bước giữa--> PENDING_APPROVAL   (đổi current_step_id)
PENDING_APPROVAL --duyệt bước cuối--> PENDING_CLOSURE_CONFIRMATION
PENDING_CLOSURE_CONFIRMATION --xác nhận đóng--> COMPLETED
PENDING_HANDLING|PENDING_APPROVAL --từ chối--> REJECTED
PENDING_HANDLING|PENDING_APPROVAL --hủy--> CANCELLED
DRAFT --xóa--> (mất bản ghi)
```

P2 chèn thêm, không đổi các cạnh trên:

- Duyệt cột mốc `FORM_COMPLETION` → `PENDING_SUPPLEMENTAL_FORM` → gửi phiếu 2 → `PENDING_APPROVAL`.
- Bước tài chính → `PENDING_PAYMENT` → kế toán xác nhận chi → `PENDING_CLOSURE_CONFIRMATION`.

Quy tắc V3 giữ nguyên: hết bước duyệt thì **bắt buộc** `PENDING_CLOSURE_CONFIRMATION`. Không có đường tắt sang `COMPLETED`.

## 7. Schema

Migration trong `Modules/DonTu/Database/migrations/`. Tên bảng logic (prefix
do DB). Khóa ngoại `user_id` → `users`, `department_id` → `departments`.

### 7.1 Master

```
don_tu_status_sets
  id, code (unique), name, description,
  is_system (bool, bộ seed không cho xóa),
  created_at, updated_at

don_tu_statuses
  id, status_set_id, code,
  label,                    -- tiếng Việt hiển thị
  group_kind,               -- main | conditional | terminal
  terminal_kind,            -- null | success | failed | draft
  sort_order,
  unique (status_set_id, code)

don_tu_transitions
  id, status_set_id,
  from_code,                -- 'NONE' cho T01/T02 từ chưa có bản ghi
  to_code, action_key,      -- SUBMIT, ACCEPT, APPROVE, REJECT, CANCEL, CLOSE, SAVE_DRAFT, REQUEST_CHANGES, SUPPLEMENT, CONFIRM_PAYMENT
  actor_policy,             -- requester | assignee | closure_actor | admin
  guard_json,               -- điều kiện: not_past_bgh, reason_min_10, form_complete, is_financial, ...
  unique (status_set_id, from_code, action_key, to_code)

don_tu_form_templates
  id, code (unique), name, description,
  scope,                    -- internal | external | both
  department_id nullable,   -- null = mẫu hệ thống
  status,                   -- draft | active | archived
  version,                  -- integer, tăng khi publish
  schema_json, branding_json,
  created_by, updated_by, timestamps

don_tu_workflows
  id, code (unique), name, description,
  status_set_id, department_id nullable,
  is_financial (bool), is_active (bool),
  initiator_policy,         -- staff | parent | both
  closure_actor_policy,     -- requester | parent | executive | role
  closure_role nullable,
  created_by, timestamps

don_tu_workflow_forms
  id, workflow_id, form_template_id,
  form_key,                 -- form_1 | form_2 | ...
  sequence,                 -- 1 = start
  is_start (bool)

don_tu_workflow_steps
  id, workflow_id, sort_order, name,
  status_group_code,        -- mã trong bộ trạng thái của workflow
  step_type,                -- receive | approve | form_completion | payment | closure | task
  target_form_keys json,    -- ["form_1"]
  assignee_policy,          -- role | user | requester_manager
  assignee_role nullable, assignee_user_id nullable,
  sla_hours nullable,
  sign_mode,                -- online | smartca | offline  (P1 chỉ online)
  workspace_task (bool),
  allow_reject (bool), allow_request_changes (bool)
```

Seed P0: `STATUS_SET_DEFAULT` với 9 trạng thái V3 và các cạnh T01–T13.
`REQUEST_CHANGES` (T13) giữ nguyên `PENDING_APPROVAL`, bật cờ revise — ghi
trong `guard_json` / side effect, không tạo mã trạng thái mới.

### 7.2 Runtime

```
don_tu_submissions
  id, reference_code nullable unique,   -- null khi DRAFT
  workflow_id, department_id, campus_code nullable,
  requester_id,
  macro_status_code,
  current_step_id nullable,             -- null khi DRAFT / terminal
  revise_open (bool), revise_note nullable, revise_fields json nullable,
  form_stage,                           -- 1 | 2
  closed_at nullable, closed_by nullable,
  submitted_at nullable,
  timestamps

don_tu_form_payloads
  id, submission_id, form_key, template_version,
  data_json, locked_at nullable,
  unique (submission_id, form_key)

don_tu_step_runs
  id, submission_id, workflow_step_id,
  assignee_id nullable,
  state,                    -- pending | done | skipped
  action_key nullable, acted_by nullable, acted_at nullable,
  note nullable, sign_mode nullable, signature_hash nullable

don_tu_status_audits          -- append-only, không update/delete từ app
  id, submission_id,
  from_code, to_code, action_key,
  step_order nullable, step_name nullable,
  actor_id, actor_role, ip, sign_mode nullable,
  notes, created_at
  -- không có updated_at

don_tu_comments
  id, submission_id, author_id, body, attachment_path nullable, created_at

don_tu_submission_tasks       -- P2
  id, submission_id, step_run_id, task_id, timestamps
```

`COMPLETED`: `data_json` và audit chỉ đọc. API từ chối mọi mutate trừ comment
nếu product muốn đóng luôn comment — **P1 đóng luôn sửa payload và comment**
sau `COMPLETED` / `REJECTED` / `CANCELLED` (khớp “immutable snapshot”).

Xóa nháp: hard delete submission DRAFT của chính requester. Hồ sơ đã có
`reference_code` không hard delete.

### 7.3 Index

- `don_tu_submissions (requester_id, macro_status_code)`
- `don_tu_submissions (department_id, macro_status_code)`
- `don_tu_step_runs (assignee_id, state)` — inbox
- `don_tu_status_audits (submission_id, id)`

## 8. Ma trận chuyển trạng thái (P1 + móc P2)

Engine đọc `don_tu_transitions`. Bảng dưới là seed `STATUS_SET_DEFAULT`.
`actor` kiểm thêm: người thực hiện phải đúng policy **và** đúng assignee của
`current_step` (trừ requester và admin override P3).

| Mã | Từ | Hành động | Ai | Đến | Guard |
|---|---|---|---|---|---|
| T01 | NONE | `SAVE_DRAFT` | requester | DRAFT | ≥ 1 field |
| T02 | NONE hoặc DRAFT | `SUBMIT` | requester | PENDING_HANDLING | đủ field bắt buộc phiếu 1 |
| T03 | PENDING_HANDLING | `ACCEPT` | assignee bước 1 | PENDING_APPROVAL | bước `receive` |
| T04 | PENDING_APPROVAL | `APPROVE` | assignee | PENDING_APPROVAL | còn bước approve sau, không phải form_completion / payment |
| T05 | PENDING_APPROVAL | `APPROVE` | assignee | PENDING_SUPPLEMENTAL_FORM | P2: `step_type=form_completion` và còn form kế |
| T06 | PENDING_SUPPLEMENTAL_FORM | `SUPPLEMENT` | requester | PENDING_APPROVAL | P2: phiếu 2 hợp lệ |
| T07 | PENDING_APPROVAL | `APPROVE` | assignee | PENDING_PAYMENT | P2: bước kế là payment và `is_financial` |
| T08 | PENDING_PAYMENT | `CONFIRM_PAYMENT` | assignee | PENDING_CLOSURE_CONFIRMATION | P2: có mã UNC |
| T09 | PENDING_APPROVAL | `APPROVE` | assignee | PENDING_CLOSURE_CONFIRMATION | không còn bước chuyên môn |
| T10 | PENDING_CLOSURE_CONFIRMATION | `CLOSE` | closure actor | COMPLETED | đã xác nhận cam kết |
| T11 | PENDING_HANDLING hoặc PENDING_APPROVAL hoặc PENDING_PAYMENT | `REJECT` | assignee | REJECTED | lý do ≥ 10 ký tự. P1 không có PENDING_PAYMENT |
| T12 | PENDING_HANDLING, PENDING_APPROVAL, PENDING_SUPPLEMENTAL_FORM | `CANCEL` | requester | CANCELLED | P1: chưa vào closure. P2: thêm “chưa qua bước đánh dấu chặn hủy” (`blocks_cancel` trên step, mặc định bước BGH) |
| T13 | PENDING_APPROVAL | `REQUEST_CHANGES` | assignee | PENDING_APPROVAL | `revise_open=true`, không đổi macro |

Side effect tối thiểu trong cùng transaction với audit:

| Action | Việc kèm |
|---|---|
| SUBMIT | sinh `reference_code`, khóa sửa phiếu 1, tạo `step_run` bước 1, notify assignee |
| ACCEPT / APPROVE giữa | đóng step_run hiện tại, mở step_run kế, notify |
| REQUEST_CHANGES | `revise_open`, lưu note + danh sách field được sửa |
| SUBMIT khi revise_open | tắt cờ, khóa lại phiếu, không sinh mã mới |
| REJECT / CANCEL | đóng step_run đang pending, notify requester (và assignee nếu hủy) |
| CLOSE | `closed_at`, `locked_at` mọi payload |

Hủy **không** áp dụng sau `PENDING_CLOSURE_CONFIRMATION`, `PENDING_PAYMENT`,
`COMPLETED`.

## 9. Phân quyền

Catalog `config/permissions.php`, `active: true` từ P1 (P0 có thể để key
chưa active nếu chưa có trang — khi có route thì bật).

| Key | Nhãn | Ai được gán mặc định |
|---|---|---|
| `don_tu.*` | Toàn bộ đơn từ | `admin` (thêm vào matrix admin) |
| `don_tu.submit` | Nộp và theo dõi đơn của mình | `member`, `team_lead`, `section_head`, `deputy_department_director`, `department_director`, `director_officer`, `admin` |
| `don_tu.approve` | Duyệt đơn được giao | cùng các role trên **trừ** không tự động cho mọi member. Member **vẫn** có `approve` vì GVCN/cán bộ có thể là assignee; API chỉ duyệt khi user là assignee của bước (quyền là cửa, assignee là khóa). |
| `don_tu.view_department` | Tra cứu hồ sơ phòng ban | `department_director`, `deputy_department_director`, `director_officer`, `admin` |
| `don_tu.manage_department` | Cấu hình quy trình/mẫu của phòng | `department_director`, `deputy_department_director`, `admin` |
| `don_tu.manage_system` | Bộ trạng thái toàn hệ thống, sửa mẫu hệ thống | reserved, chỉ `super_admin` qua `*` |

`viewer` không nộp, không duyệt.

`don_tu.manage_system` đưa vào `reserved` (cùng kiểu `feature_request.review`).

Kiểm tra API, không chỉ ẩn menu:

- Sửa/xóa nháp: requester và `macro_status=DRAFT`.
- Duyệt: `don_tu.approve` và `step_run.assignee_id = user` và `state=pending`.
- Tra cứu: `don_tu.view_department` và `submission.department_id` thuộc phạm vi role (admin/director_officer: không lọc phòng; trưởng phòng: đúng phòng).
- Cấu hình mẫu/quy trình phòng: `don_tu.manage_department` và `department_id` khớp.

RBAC theo trạng thái (spec V) là lớp thứ hai trong `StatusTransitionService`,
không thay permission key.

## 10. API

Prefix `Route::middleware('web')->prefix('api')->name('api.don_tu.')`.
File `Modules/DonTu/routes/manager.php`. JSON, session cookie như Report.

### P0

| Method | Path | Việc |
|---|---|---|
| GET | `/api/don-tu/status-sets` | Danh sách bộ + trạng thái + transition. `manage_system` hoặc `manage_department` (department chỉ đọc). |

### P1

| Method | Path | Việc |
|---|---|---|
| GET | `/api/don-tu/workflows` | Quy trình `is_active` user được khởi tạo |
| GET | `/api/don-tu/workflows/{id}/start-form` | Schema phiên bản active của form_1 |
| POST | `/api/don-tu/submissions` | Tạo DRAFT hoặc SUBMIT (`intent`) |
| GET | `/api/don-tu/submissions/mine` | Phân trang, lọc trạng thái |
| GET | `/api/don-tu/submissions/{id}` | Chi tiết nếu requester, assignee từng bước, hoặc view_department |
| PATCH | `/api/don-tu/submissions/{id}` | Sửa payload khi DRAFT hoặc revise_open |
| POST | `/api/don-tu/submissions/{id}/actions` | Body `{ action, note?, fields? }` — một cửa cho ACCEPT, APPROVE, REJECT, CANCEL, CLOSE, REQUEST_CHANGES, SUBMIT |
| GET | `/api/don-tu/inbox` | Step_run pending của user |
| GET | `/api/don-tu/inbox/count` | Số cho badge |
| GET | `/api/don-tu/submissions/{id}/audits` | Timeline |
| POST | `/api/don-tu/submissions/{id}/comments` | Trao đổi, chặn khi terminal |

Lỗi nghiệp vụ: HTTP 422 `{ message, code }` với `code` = `TRANSITION_DENIED`,
`VALIDATION`, `NOT_ASSIGNEE`, `LOCKED`.

### P2 (không làm trong P1, chốt path sớm để khỏi đổi)

- CRUD `/api/don-tu/form-templates`, `/api/don-tu/workflows` (write).
- `POST .../actions` thêm `SUPPLEMENT`, `CONFIRM_PAYMENT`.
- `GET /api/don-tu/registry` lọc phòng/trạng thái/quy trình.

## 11. UI

Bám rule workspace: light only; danh sách theo mẫu `ActivityLog.vue` +
`TablePagesBar`; form modal 2–3 cột theo mẫu modal tiêu chí
`WorkspaceConfigEvaluation.vue`; confirm hủy/từ chối là dialog nhỏ, không dùng
form-modal full.

| Màn | Khung |
|---|---|
| Nộp đơn | Lưới thẻ quy trình được phép. Chọn thẻ → form render từ schema. Nút Lưu nháp / Gửi đơn. |
| Hồ sơ của tôi, Cần tôi duyệt, Tra cứu | Bảng: Mã hồ sơ, Quy trình, Người nộp (tên + email một cột), Trạng thái, Cập nhật. Cột ít. Panel phải 28rem. Dropdown Thao tác một nút `more_vert`. Kéo cột, `useDragScroll`, `hide-scrollbar`. |
| Chi tiết hồ sơ | Không phải bảng. Stepper chỉ vẽ trạng thái vĩ mô của bộ đã gán. Dưới stepper: bước hiện tại (tên, người giữ, SLA). Tab: Phiếu, Ý kiến & ký, Lịch sử. Banner khi `PENDING_CLOSURE_CONFIRMATION`. |
| Cấu hình P1 | Danh sách quy trình (bảng) + panel đọc bộ trạng thái. P2 mới mở modal builder. |
| Từ chối / Hủy | Dialog: lý do bắt buộc, từ chối min 10 ký tự. |

Trạng thái badge map nhãn từ `don_tu_statuses.label`, không hard-code tiếng
Việt trong Vue trừ fallback khi API lỗi.

Người nộp trên bảng: tên dòng trên, email dòng dưới màu mờ — một cột.

## 12. Tích hợp

| Hệ thống | P1 | Sau |
|---|---|---|
| Identity user / department | requester, assignee, scope | snapshot chức danh lúc ký (P2) |
| UserNotification | SUBMIT, chuyển lượt, REJECT, CANCEL, CLOSE | |
| ActivityLog | đổi cấu hình mẫu/quy trình (P2); P1 ghi audit riêng `don_tu_status_audits` | override admin |
| Project task | không | bước `workspace_task` tạo task, lưu `don_tu_submission_tasks` |
| App Phụ huynh | không | webhook `don_tu.status_changed` |

Thông báo dùng cơ chế notification hiện có. Payload: mã hồ sơ, nhãn trạng
thái, link `/manager/don-tu/mine` hoặc inbox.

## 13. Kế hoạch triển khai

Checklist tick ở **§0**. Mục này chỉ giữ thứ tự và điều kiện xong đợt.
Thứ tự bắt buộc: P0 → P1. P2/P3 không bắt đầu trước khi P1 duyệt được một hồ
sơ thật trên môi trường dev.

### P0 — Khung (ước lượng 3–4 ngày)

Việc: §0.5. **Xong khi** sáu dòng §0.5 đều tick. Hạng mục §0.3 vẫn 0/29.

### P1 — Một quy trình nội bộ (ước lượng 2–3 tuần)

Tick §0.3: F-REQ-01, F-REQ-02, F-REQ-03, F-REQ-04, F-APR-01, F-APR-03.
Tick màn UI-01 đến UI-05 và hộp thoại D1 đến D5.

Kèm theo, chưa phải hạng mục riêng: seeder quy trình pilot (tiêu đề, nội dung,
ngày), `StatusTransitionService`, API mục 10, thông báo, feature test T01, T02,
T03, T04, T09, T10, T11, T12, T13.

**Xong P1 khi:** member gửi đơn → assignee tiếp nhận và duyệt → requester xác
nhận đóng → trạng thái Hoàn thành, payload không sửa được, inbox hết badge.

Pilot cụ thể đề xuất (đổi được nếu BA chỉ định đơn khác, miễn không cần phiếu
2 và không cần tiền):

| Bước | Tên | status_group_code | step_type | Assignee |
|---|---|---|---|---|
| 1 | Tiếp nhận | PENDING_HANDLING → sau ACCEPT sang PENDING_APPROVAL | receive | role trưởng nhóm / trưởng phòng của department người nộp |
| 2 | Phê duyệt | PENDING_APPROVAL | approve | trưởng phòng |
| 3 | Xác nhận đóng | PENDING_CLOSURE_CONFIRMATION | closure | requester |

Bước 1 khi đang `PENDING_HANDLING` là bước hiện tại; sau ACCEPT,
`macro_status` = `status_group_code` của bước 2 (`PENDING_APPROVAL`). Khớp
T03. Bước 3 không “duyệt”, chỉ `CLOSE` (T10).

### P2 — Builder + tuần tự + tiền + task (ước lượng 4–6 tuần)

Chia PR: (1) form builder, (2) workflow builder, (3) supplemental form,
(4) payment block, (5) task sync, (6) registry. Mỗi PR có test transition
riêng T05–T08.

### P3 — Phụ huynh + SLA + ký số

Chỉ sau khi hợp đồng API với app phụ huynh được chốt (payload, idempotency
key, retry). Không thiết kế hàng đợi chi tiết trong plan này.

### File chạm ở P0 (danh sách khóa)

| File | Việc |
|---|---|
| `modules_statuses.json` | bật DonTu |
| `resources/js/router/index.js` | spread `donTuRoutes` |
| `resources/js/components/AppSidebar.vue` | item + badge |
| `config/permissions.php` | catalog + matrix |
| `DepartmentSidebarConfigService.php` | whitelist menu |
| `GlobalMenuVisibilityService.php` | catalog |
| `sidebarMenus.js` | catalog UI |
| `Modules/DonTu/**` | module mới |

Không sửa `Home.vue` ở P0.

## 14. Rủi ro và điểm cần người yêu cầu xác nhận

| # | Điểm | Mặc định nếu không trả lời |
|---|---|---|
| C1 | Pilot là đơn nội bộ ngắn hay đơn bán trú / hoàn phí? | Đơn nội bộ ngắn (D9). Bán trú kéo theo phụ huynh (P3). |
| C2 | Có gộp mục sidebar 「Quy trình」 (`home`) vào Đơn từ không? | Không. Giữ `/`. |
| C3 | Member có được hiện inbox không nếu chưa được giao hồ sơ nào? | Có, tab inbox hiện, danh sách trống. Badge ẩn khi count = 0. |
| C4 | Ai là người đóng đơn pilot? | Chính người nộp. |
| C5 | Hủy được đến trước bước nào? | Trước closure. Khi có bước BGH, step đó `blocks_cancel=true`. |
| C6 | Mã hồ sơ có cần mã cơ sở thật không? | Nếu user chưa có campus: dùng mã phòng ban. |
| C7 | SmartCA có phải P1 không? | Không (D7). |

Rủi ro kỹ thuật:

- Hai spec trạng thái lệch mã. Đã chốt D5. Nếu BA muốn bộ V2 §4.1 làm mặc định, phải sửa seed trước P1 — đừng song song hai bộ “default”.
- Assignee “trưởng phòng của người nộp” phụ thuộc dữ liệu department/role đúng. Test phải dựng user có department, không chỉ super_admin.
- `process.*` còn trong matrix. Dễ bị hiểu nhầm là đã có module. Không bật `active` cho `process.*`.

## 15. Tiêu chí nghiệm thu P1 (DoD)

1. Menu 「Đơn từ」 hiện với member và trưởng phòng, ẩn với viewer.
2. Member lưu nháp, sửa, xóa nháp. Nháp không có mã `HS-` và không notify.
3. Gửi đơn sinh đúng một mã, trạng thái 「Chờ xử lý」, trưởng phòng thấy ở inbox.
4. Người không phải assignee gọi `APPROVE` nhận 422.
5. Duyệt bước cuối chuyển 「Chờ xác nhận hoàn tất」, không nhảy 「Hoàn thành」.
6. Người nộp xác nhận đóng → 「Hoàn thành」, PATCH payload trả 422.
7. Từ chối thiếu lý do hoặc lý do ngắn hơn 10 ký tự trả 422; đủ lý do → 「Từ chối」 và notify người nộp.
8. Mỗi chuyển trạng thái có đúng một dòng `don_tu_status_audits`.
9. UI danh sách có `TablePagesBar`, panel 28rem, một nút Thao tác. Không dark mode.
10. Feature test PHP phủ các cạnh P1 ở mục 13.
