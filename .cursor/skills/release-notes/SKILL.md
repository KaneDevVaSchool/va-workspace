---
name: release-notes
description: >-
  Lập phiếu release VA Workspace từ toàn bộ commit git: thư mục theo ngày,
  phiếu tuần trong release/summary, và audit.md. Dùng khi người dùng nói tổng
  hợp commit, phiếu release, chia theo ngày, tổng kết tuần, audit tuần, vừa
  chỉnh một chức năng, hoặc thư mục release.
---

# Phiếu release từ commit

Rule: `.cursor/rules/release-notes.mdc`. Cùng kiểu VA-HRM (`va-hrm/.cursor/skills/release-notes/SKILL.md`), đường dẫn module là `Modules/<Tên>/`.

Mẫu: `release/README.md`, `release/summary/2026-W44-summary.md`, `release/2026-10-29/README.md`.

## Khi vừa chỉnh một chức năng

1. Commit trên nhánh đang làm. Message là một câu nghiệp vụ (`feat(attendance): …`), không phải `a` / `updating`. Commit một chữ chỉ thành dòng «Sửa tiếp …», không vào **Tính năng lớn**.
2. Chạy script ở dưới. Không sửa tay phiếu ngày hay phiếu tuần.
3. Mở `release/summary/` của tuần ISO hiện tại (thứ 2 → Chủ nhật). Đọc **Module** và **Tính năng lớn**.
4. Mở `release/YYYY-MM-DD/README.md` để đọc tiếng thường, và `audit.md` cùng ngày để đối soát bảng. Cả tuần: `release/summary/YYYY-Wnn-audit.md`.
5. Việc chưa commit không có trên phiếu. Nếu người dùng bảo cập nhật mà chưa commit, commit **đúng file của việc đó** (message một câu nghiệp vụ). Không commit `tmp/`, không gom file đang dở khác, không commit cây `release/` trừ khi được hỏi.
6. Đối chiếu phiếu ngày với `docs/architecture/modules/<Tên>.md`. Tên màn trên phiếu phải là `meta.title` của trang Vue ghi trong docs. Lệch thì sửa `SCREEN_RULES` trong script: trang `.vue` và tên controller cụ thể đứng trước tiền tố chung (`EmployeeLeaveRequest` trước `EmployeeLeave` — nếu không, đơn nghỉ bị ghi thành màn Nghỉ phép). Chạy lại script. Không sửa tay phiếu.

## Khi nào chạy

Người dùng muốn gom commit thành `release/` (cả lịch sử hoặc làm lại phiếu). Chạy lại script, không viết tay đè phiếu ngày.

## Cách làm

Từ gốc repo:

```bash
node .cursor/skills/release-notes/scripts/build-release-notes.mjs
```

Script gọi `git.exe` (không gọi `git.cmd` — cmd nuốt `%h` trong `--pretty`).

| File | Nội dung |
| --- | --- |
| `release/README.md` | Mục lục tuần + ngày, tổng commit, HEAD, tên nhánh |
| `release/summary/YYYY-Wnn-summary.md` | Cửa sổ thứ 2–Chủ nhật, bảng ngày, module, tính năng lớn |
| `release/summary/YYYY-Wnn-audit.md` | Mỗi việc một bảng Đổi gì / Vì sao / Tác dụng, số version 1.1, 1.2 |
| `release/YYYY-MM-DD/README.md` | Phiếu ngày tiếng thường |
| `release/YYYY-MM-DD/audit.md` | Việc trong ngày số **1.1**, **1.2**. Mỗi việc một bảng Đổi gì / Vì sao / Tác dụng, rồi bảng lần lưu |

Script xóa rồi ghi lại thư mục `release/20xx-xx-xx`, `release/summary/`, và phiếu tuần cũ nằm ở gốc `release/`. Không đụng file khác.

## Luật diễn giải

- Message đủ nghĩa thành một nhóm việc. Commit một chữ ngay sau, cùng vùng file, nhập vào nhóm đó.
- Vùng file: `Modules/<Tên>/`. Còn lại: tài liệu, kiểm thử, nền tảng, giao diện gốc, tooling.
- Phiếu ngày viết tiếng thường: «lần lưu», «màn hình», «đổi dữ liệu». Bỏ tiền tố `feat` / `fix`. Commit một chữ ghi «Sửa tiếp cùng việc» và tên màn nếu nhận ra. Không suy nghiệp vụ ngoài câu commit và màn hình đã gắn.
- Màn hình lấy từ trang Vue (`Modules/*/resources/js/pages/*.vue`, `meta.title`). Phần (cột Module) vẫn là thư mục `Modules/<Tên>/` — đơn nghỉ nằm trong Attendance nên phần là Chấm công, màn là Lịch sử.
- Audit (`audit.md` ngày và `YYYY-Wnn-audit.md` tuần), cùng kiểu VA-HRM: mỗi việc một bảng **Đổi gì** (câu commit, kèm thân commit nếu có), **Vì sao** (mệnh đề vì / để / do / because / so that), **Tác dụng** (mệnh đề sau để / so that, hoặc câu migration). Không có thì ghi «Chưa ghi lý do trong commit» hoặc «Chưa ghi tác dụng trong commit». «chỉ để» (giữ lại) không tính là lý do. Không suy diễn.
- Phần của một việc lấy module code có nhiều file nhất. File `docs/` và tooling không kéo phần thành «Tài liệu» khi commit còn đụng module.
- Số việc trong một ngày là version **1.1**, **1.2**, … (không đánh 1, 2).
- Tuần ISO, thứ 2 → Chủ nhật. Ngày 0 commit vẫn có dòng trên phiếu tuần.

## Xong

Báo số commit, số tuần, số ngày, và đường dẫn `release/README.md` cùng phiếu tuần hiện tại.
