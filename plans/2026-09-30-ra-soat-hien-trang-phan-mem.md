# Rà soát hiện trạng phần mềm — Điểm đã có / Điểm chưa có (30/09/2026)

> **Trạng thái: RÀ SOÁT** — không phải kế hoạch triển khai. Chụp lại hiện trạng
> code thực tế tại 30/09/2026 để đối chiếu với `docs/VA_WORKSPACE_OVERVIEW.md`
> (viết 24/08/2026, đã lạc hậu ở nhiều mục).

## 1. Tóm tắt

Rà soát trực tiếp trong code: **12 module** đang chạy, **58 file test**,
**128 migration**. So với roadmap §19 của overview doc:

- **4 module dựng ngoài roadmap** và chưa có trong bảng §19: `Chat`,
  `Credential`, `Dashboard`, `Report`, `FeatureRequest` (5 module).
- **3 mục doc ghi "chưa có" nhưng thực tế ĐÃ CÓ**: Gantt UI, Sprint board,
  tích hợp HRM.
- **14 module trong roadmap vẫn chưa dựng.**
- Doc `VA_WORKSPACE_OVERVIEW.md` **cần cập nhật** — nhiều mục lệch thực tế.

## 2. ĐÃ CÓ — 12 module đang chạy

### 2.1. Nền tảng (Phase 0–1, khớp doc)

| Module | Nội dung | Test |
|---|---|---|
| `Identity` | Google SSO + **HRM SSO**, 9 role, RBAC engine + ma trận quyền, View-as, Team, nhật ký hoạt động, shortcut, thông báo, Web Push | 8 |
| `WorkspaceConfig` | Hub trưởng phòng (thành viên, nhóm, gán vai trò, sidebar), superadmin overview, menu hệ thống, nhân sự chưa gắn phòng | 4 |

### 2.2. Nghiệp vụ

| Module | Nội dung | Test |
|---|---|---|
| `Project` | CRUD dự án, Task WBS đa cấp, "Tất cả công việc", Sprint board, **Gantt**, worklog, testcase, feedback, tài liệu, bình luận đa cấp + cảm xúc, import/export Excel, chế độ Lịch/Kanban, delegation hàng loạt | 9 |
| `Social` | Bảng tin 4 loại tường, cảm xúc, bình luận đa cấp, poll, nhóm, sticker/GIF, `@mention`, ghim, hashtag, lượt xem, duyệt bài | **20** |
| `Evaluation` | Tiêu chí đánh giá (2 kiểu), khung điểm, cấu hình theo phòng ban, trường tùy biến | 7 |
| `Report` | Báo cáo đánh giá nhân sự: cột/tiêu chí/filter/người xem cấu hình được, snapshot theo người | 3 |
| `Dashboard` | 3 dashboard: Của tôi / Phòng ban / Tổng công ty — KPI card, donut + bar chart, sức khoẻ dự án, bảng dự án + nhân sự | **0** |
| `Chat` | Nhắn tin 1-1 + nhóm, đính kèm, ẩn tin, panel nổi | 3 |
| `Credential` | Quản lý tài khoản/mật khẩu dùng chung, nhà cung cấp, phân quyền người xem | 1 |
| `FeatureRequest` | Ghi nhận yêu cầu tính năng, duyệt/từ chối/hoàn thành, export Excel | 2 |

### 2.3. Tích hợp VA-HRM — ĐÃ CÓ (doc ghi "stub chờ HRM")

Đây là thay đổi lớn nhất so với doc. Đã dựng cả subsystem tại
`Modules/Identity/App/Hrm/`:

- **SSO**: `HrmSsoController` + `HrmSsoService` + `HrmJwtVerifier` (xác thực
  JWT qua JWKS), route `/auth/hrm/redirect` + `/auth/hrm/callback`.
- **Webhook**: `HrmWebhookController` + `VerifyHrmWebhookSignature` (HMAC),
  `HrmWebhookDispatcher`, bảng `hrm_webhook_deliveries` (có retry/log).
- **Đồng bộ**: `HrmEmployeeSyncService`, `HrmEmployeeBulkSyncService`,
  `HrmDepartmentSyncService`, `HrmApiClient`, DTO `HrmEmployeeData` /
  `HrmAssignmentData`.
- **Schema**: `companies`, `user_concurrent_positions` (kiêm nhiệm), cột HRM
  thêm vào `departments` + `users` (migration `2026_10_01_*`).
- **Console**: `DiagnoseHrmSsoCommand`, `WarmHrmJwksCommand`.

→ Ghi chú memory "HRM employee sync (future) — chưa code" **đã lạc hậu**.

### 2.4. Hạ tầng

- **Realtime**: Pusher đã cấu hình (`config/broadcasting.php`, `pusher-js` trong
  `package.json`) — rủi ro §20.10 "chưa có realtime notification" đã giải quyết.
- **Web Push**: `WebPushService` + bảng `push_subscriptions`.
- **Export Excel**: đã có ở Activity log, Task, Project, Report, FeatureRequest.

## 3. CHƯA CÓ

### 3.1. Module trong roadmap chưa dựng (14)

| Phase | Module | Nội dung |
|---|---|---|
| 2 | `Initiative` | Hạng mục — giao/nhận liên phòng ban, roll-up trạng thái (§5) |
| 4 | `TaskScoringConfig` + `Kpi` | 3 hệ điểm theo phòng ban + KPI dashboard (§7) |
| 5 | `DailyReport` | Báo cáo ngày |
| 5 | `Blocker` | Điểm nghẽn |
| 5 | `Contract` | Hợp đồng |
| 5 | `KnowledgeBase` | Cơ sở tri thức |
| 5 | `AiAccount` | Tài khoản AI |
| 5 | `WeeklyReport` | Báo cáo tuần |
| 5 | Evaluation Giai đoạn D | Phiếu đánh giá thực tế, hội đồng, kỳ đánh giá |
| 6 | `Onboarding` | Hướng dẫn cho 9 role — **liên quan feedback STT 23** |
| 8 | `DocumentManager` | Quản lý tài liệu tách lớp (§18) |
| 9 | `ProjectFinance` | Tài chính dự án (§15) — **liên quan feedback STT 5** |
| 11 | `MaterialPlanning` | Vật tư & dự toán (§14) |
| 12 | `ProcessEngine` | Quy trình động (§13) |

Lưu ý: `Report` đã dựng nhưng là **báo cáo đánh giá nhân sự**, không phải
`DailyReport`/`WeeklyReport` của Phase 5, cũng không phải Performance Report
theo dự án (§17, Phase 10).

### 3.2. Thiếu trong module đã có

| Việc | Hiện trạng | Ảnh hưởng |
|---|---|---|
| **Tiến độ dự án lệch giữa 2 trang** *(đính chính 30/09)* | Roll-up **đã có**: `Modules/Dashboard/App/Services/ProjectProgressCalculator.php` cài đủ 3 phương pháp (`average`/`duration_weighted`/`task_weighted`) + fallback. **Nhưng** chỉ `CompanyDashboardService` dùng; `ProjectService.php:988` (hàm `present()`) vẫn hardcode `'progress_percent' => null` kèm comment sai *"Chưa có Task — luôn null ở giai đoạn 1"* | Dashboard hiện **đúng** % tiến độ, còn `/manager/project` + chi tiết dự án luôn hiện `—` (`ProjectList.vue:1125`). Là **lỗi nhất quán dữ liệu**, không phải thiếu tính năng. Liên quan feedback STT 4 |
| **Task Delegation accept/reject** | Chỉ có bulk delegate 1 chiều + thông báo. Người nhận **không** accept/reject được | Phase 3 mới xong một phần |
| **`progress_type` 3 kiểu mới** | `checklist`/`child_weight`/`timeline` đã khai enum + validate + **hiện trên UI**, nhưng `applyQuantityProgress()` chưa có logic tính | Người dùng chọn được nhưng `progress_percent` đứng yên — **lỗi tiềm ẩn đang phơi ra UI** |
| **Creation settings enforcement** | 10 cột cấu hình ẩn/hiện chéo đã có + validate, nhưng chưa rà soát hết nơi **thực thi** | Cờ bật mà không có tác dụng |
| **Test Dashboard** | **0 test** cho toàn module Dashboard | Module hiển thị số liệu điều hành, sai số không ai phát hiện |
| **Siết phạm vi người nhận delegation** | `BulkDelegateTaskRequest` chỉ validate `exists:users,id` | Ai có `task.delegate` giao được cho **bất kỳ user toàn hệ thống** |
| **Transaction cho bulk actions** | `bulkUpdate()`/`bulkDelegate()` lặp N query, không bọc `DB::transaction()` | Lỗi giữa chừng → dữ liệu nửa vời, không rollback |

### 3.3. Nợ kỹ thuật (từ `docs/known-issues.md`, đã xác minh còn)

- **Laravel 10 hết hạn vá bảo mật** — đã tắt `block-insecure` để cài được.
  Đây **không phải** đã vá. Cần đánh giá nâng lên 11/12.
- **esbuild/vite advisory** — chỉ ảnh hưởng `npm run dev`.
- **Vi phạm §5 CLAUDE.md** — Service/Controller gọi Eloquent trực tiếp ở
  `TaskService`, `PermissionMatrixController`, `ViewAsController`,
  `SuperAdminBootstrap`, và 3 service của Social.
- **Filter Lịch không dùng được index** — `whereRaw('DATE(COALESCE(...)))`.

### 3.4. Câu hỏi chưa chốt (§20) còn treo

Các mục **chưa chốt** vẫn chặn Phase 2+:

- §20.1 — `director_officer` có gắn phòng ban ảo hay phi phòng ban?
- §20.3 — roll-up trọng số Hạng mục có phạt điểm khi trễ hạn?
- §20.4 — Task delegated tính KPI cho phòng nguồn hay phòng nhận?
- §20.5 — `viewer` có cần scope theo Hạng mục?
- §20.6/7 — Process Engine: khoá sửa template khi đang chạy? ai được tạo?
- §20.8 — `project_expenses` có cần workflow duyệt?
- §20.9 — WBS giới hạn mấy tầng?

§20.2 (Team), §20.10 (lib realtime), §20.11 (Sanctum) **đã chốt/đã giải quyết**.

## 4. Doc cần cập nhật

`docs/VA_WORKSPACE_OVERVIEW.md` lệch thực tế ở các điểm sau — theo
`plans/README.md`, `docs/` là *nguồn sự thật hiện tại, sai lệch với code là bug
tài liệu cần sửa ngay*:

1. **Bảng "Đã có trong repo" (§ Trạng thái triển khai)** — thiếu hẳn 5 module
   `Chat`, `Credential`, `Dashboard`, `Report`, `FeatureRequest`.
2. **Câu mở đầu** *"Các module nghiệp vụ (Project, Initiative, DailyReport…)
   chưa dựng"* — Project đã dựng rất đầy đủ.
3. **§19 Phase 1g** ghi *"chưa có Sprint/Worklog/Gantt UI thật"* — cả 3 đã có
   (`ProjectGanttTab.vue` 1.646 dòng, `ProjectSprintBoard.vue` 1.261 dòng,
   worklog trong `TaskDetail.vue`).
4. **§19 Phase 7** ghi "Chưa" cho cả Gantt + roll-up — Gantt xong, roll-up chưa.
   Cần tách trạng thái.
5. **Dòng `Identity`** ghi *"User/Department (stub chờ HRM)"* — HRM SSO +
   webhook + sync đã dựng đầy đủ (§2.3).
6. **§20.10** ghi realtime notification chưa có lib — Pusher đã cấu hình.
7. **`docs/known-issues.md`** — cần thêm 2 mục: roll-up Project còn hardcode
   null, và Dashboard không có test.

## 5. Đối chiếu với feedback người dùng

Nối kết giữa rà soát này và `plans/2026-09-30-ra-soat-feedback-nguoi-dung.md`:

| Feedback | Chặn bởi điểm chưa có nào |
|---|---|
| STT 4 (CEO xem % hoàn thành dự án) | Roll-up **đã có** nhưng chưa dùng ở module Project (§3.2) — cần nối `ProjectProgressCalculator` vào `ProjectService::present()`, không phải viết mới |
| STT 5 (Dashboard Tổng Giám đốc: doanh thu, dòng tiền, công nợ) | `ProjectFinance` (Phase 9) + nguồn dữ liệu tài chính **chưa có**. Phần nhân sự/turnover giờ **có thể làm được** nhờ HRM đã tích hợp |
| STT 23 (hướng dẫn + tour onboarding) | Module `Onboarding` (Phase 6) chưa dựng |
| STT 1/4 (lọc sức khoẻ công việc/dự án) | Cần định nghĩa công thức sức khoẻ — liên quan §20.3 chưa chốt |
| STT 24 (rà soát bảng phân quyền khớp sidebar) | Không bị chặn — làm được ngay |

## 6. Khuyến nghị thứ tự

1. **Cập nhật doc** (§4) — rẻ, và đang gây hiểu sai về hiện trạng.
2. **Nối `ProjectProgressCalculator` vào module Project** — mở đường cho STT 4 +
   STT 5, và xoá 1 comment sai trong code. Calculator đã có, chỉ chưa dùng.
3. **Logic `progress_type` hoặc tạm ẩn 3 kiểu chưa cài** — hiện đang phơi lựa
   chọn không hoạt động ra UI thật.
4. **Test cho Dashboard** — module số liệu điều hành mà 0 test.
5. **Sửa nhóm lỗi feedback** (plan riêng, bước 1–4).
6. Chốt §20.1/3/4/5 rồi mới mở Phase 2 (`Initiative`) / Phase 4 (`Kpi`).
