# Tuần 44 — 26/10–1/11/2026

| | |
| --- | --- |
| **Cửa sổ** | Thứ 2 26/10/2026 → Chủ nhật 01/11/2026 |
| **Commit** | **108** |
| **Ngày có commit** | 6 |
| **Nhánh đối soát** | `main` |
| **Bảng audit** | [2026-W44-audit](./2026-W44-audit.md) |

## Theo ngày

| Ngày | Thứ | Commit | Nhóm việc | Phiếu |
| --- | --- | ---: | ---: | --- |
| **26/10/2026** | Thứ 2 | 17 | 8 | [`2026-10-26`](../2026-10-26/README.md) |
| **27/10/2026** | Thứ 3 | 30 | 17 | [`2026-10-27`](../2026-10-27/README.md) |
| **28/10/2026** | Thứ 4 | 29 | 14 | [`2026-10-28`](../2026-10-28/README.md) |
| **29/10/2026** | Thứ 5 | 17 | 5 | [`2026-10-29`](../2026-10-29/README.md) |
| **30/10/2026** | Thứ 6 | 13 | 13 | [`2026-10-30`](../2026-10-30/README.md) |
| **31/10/2026** | Thứ 7 | 2 | 2 | [`2026-10-31`](../2026-10-31/README.md) |
| **01/11/2026** | Chủ nhật | 0 | 0 | — |

## Module

| Module | Commit |
| --- | ---: |
| Chấm công | 16 |
| Chat | 24 |
| Bảng tin | 8 |
| Định danh và phân quyền | 1 |
| Đơn từ | 31 |
| Tổng quan | 1 |
| Nền tảng | 2 |
| Giao diện gốc | 15 |
| Tài liệu | 6 |
| Khác | 4 |

## Tính năng lớn

| Ngày | Module | Tính năng | Commit |
| --- | --- | --- | ---: |
| 26/10/2026 | Giao diện gốc | Nới giao diện và bỏ safe-area iOS | 3 |
| 26/10/2026 | Chấm công | Onboarding và splash boot theo mẫu welcome VA | 1 |
| 26/10/2026 | Giao diện gốc | Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp | 1 |
| 26/10/2026 | Khác | Script prod-sync-code khi git pull bi chan boi sua local | 2 |
| 26/10/2026 | Đơn từ | Thêm hub xem trước Đơn từ và kế hoạch thiết kế chi tiết | 5 |
| 27/10/2026 | Đơn từ | Gom hub cấu hình đơn từ vào thiết lập trạng thái: redirect /config, content header và bỏ liên kết mẫu biểu/quy trình trên trang admin | 1 |
| 27/10/2026 | Đơn từ | Khôi phục hub /config và thêm nút Về cấu hình trên header các màn cấu hình con | 1 |
| 27/10/2026 | Đơn từ | Thiết kế lại thanh chọn bộ trạng thái admin và Việt hoá nhãn mã trên giao diện | 1 |
| 27/10/2026 | Đơn từ | Gọn thanh chọn bộ trạng thái: bỏ badge và hint thừa, bố cục danh sách rõ hơn | 1 |
| 27/10/2026 | Đơn từ | Đổi sơ đồ trạng thái sang flow zigzag và hiện lối vào, lối ra ngay khi chọn bước | 1 |
| 27/10/2026 | Đơn từ | Gộp tiêu đề bộ trạng thái, số liệu và tab xem vào một hàng | 1 |
| 27/10/2026 | Đơn từ | Thu gọn sidebar bộ trạng thái, mở chi tiết bước bằng drawer, và đổi tab thành dải người phụ trách từng bước | 1 |
| 27/10/2026 | Đơn từ | Gọn drawer chi tiết bước, thu tóm tắt bộ vào mục đóng mở, thêm xóa bộ trạng thái, và bỏ nút sao chép link giới thiệu trên đăng nhập | 1 |
| 27/10/2026 | Đơn từ | Thêm nút sửa bộ trạng thái để đổi tên, mã và mô tả ngay trên bộ đang chọn | 1 |
| 27/10/2026 | Đơn từ | Tách sơ đồ và người phụ trách theo hướng thành công hoặc hủy, và chỉ thêm trạng thái từ menu của bộ đã chọn | 2 |
| 28/10/2026 | Chat | Mở rộng chat nhân viên (nhóm, poll, đính kèm, read receipt) và story social với sticker VA | 3 |
| 28/10/2026 | Giao diện gốc | Củng cố PWA, service worker và Web Push cho khu vực nhân viên | 1 |
| 29/10/2026 | Bảng tin | Bổ sung tab bài đã duyệt và nâng cấp trang duyệt bài Social | 1 |
| 29/10/2026 | Chấm công | Chuông thông báo shell mobile nhân viên và cải thiện lịch sử chấm công | 1 |
| 30/10/2026 | Chấm công | Xuất PDF đơn nghỉ phép đã duyệt theo mẫu BM.01 | 1 |
| 30/10/2026 | Chấm công | Giữ cơ sở hồ sơ khi chấm công, chỉ gửi chi nhánh khi nhân viên chọn cơ sở khác | 1 |
| 30/10/2026 | Chấm công | Chỉnh mẫu PDF đơn nghỉ phép có quốc hiệu, chỗ ký và watermark | 1 |
| 30/10/2026 | Chat | Ô soạn không render lại danh sách tin, kéo tin sát bàn phím | 1 |
| 30/10/2026 | Bảng tin | Khung gợi ý nhắc tên neo theo ô soạn, không bị thẻ bài cắt | 1 |
| 30/10/2026 | Bảng tin | Nhận link YouTube dạng shorts, live và nocookie, gắn origin lúc phát để khỏi lỗi 153 | 1 |
| 30/10/2026 | Bảng tin | Implement colleague tagging feature with mentions support | 1 |
| 30/10/2026 | Bảng tin | Nhóm bảng tin theo pháp nhân VM, Viễn Đông, Hoàng Việt và CIT | 1 |
| 30/10/2026 | Tổng quan | Đẩy yêu cầu sửa hồ sơ sang hàng đợi HCNS | 1 |
| 30/10/2026 | Chấm công | Unify 'hired_at' and 'actual_start_date' fields in employee profile | 1 |

## Việc trong tuần

### 26/10/2026

- **1.1.** Nới giao diện và bỏ safe-area iOS (3 lần lưu)
- **1.2.** Onboarding và splash boot theo mẫu welcome VA (1 lần lưu)
- **1.3.** Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) (1 lần lưu)
- **1.4.** Sửa tiếp phần Chấm công (chưa ghi câu mô tả) (1 lần lưu)
- **1.5.** Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) (3 lần lưu)
- **1.6.** Tab bar PWA sát đáy; overlay sinh nhật đồng nghiệp (1 lần lưu)
- **1.7.** Script prod-sync-code khi git pull bi chan boi sua local (2 lần lưu)
- **1.8.** Thêm hub xem trước Đơn từ và kế hoạch thiết kế chi tiết (5 lần lưu)

### 27/10/2026

- **1.1.** Sửa tiếp phần Đơn từ (chưa ghi câu mô tả) (10 lần lưu)
- **1.2.** Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) (1 lần lưu)
- **1.3.** Sửa tiếp phần Nền tảng (chưa ghi câu mô tả) (1 lần lưu)
- **1.4.** Sửa tiếp phần Bảng tin (chưa ghi câu mô tả) (1 lần lưu)
- **1.5.** Sửa tiếp phần Đơn từ (chưa ghi câu mô tả) (3 lần lưu)
- **1.6.** Sửa tiếp phần Nền tảng (chưa ghi câu mô tả) (1 lần lưu)
- **1.7.** Sửa tiếp phần Đơn từ (chưa ghi câu mô tả) (2 lần lưu)
- **1.8.** Gom hub cấu hình đơn từ vào thiết lập trạng thái: redirect /config, content header và bỏ liên kết mẫu biểu/quy trình trên trang admin (1 lần lưu)
- **1.9.** Khôi phục hub /config và thêm nút Về cấu hình trên header các màn cấu hình con (1 lần lưu)
- **1.10.** Thiết kế lại thanh chọn bộ trạng thái admin và Việt hoá nhãn mã trên giao diện (1 lần lưu)
- **1.11.** Gọn thanh chọn bộ trạng thái: bỏ badge và hint thừa, bố cục danh sách rõ hơn (1 lần lưu)
- **1.12.** Đổi sơ đồ trạng thái sang flow zigzag và hiện lối vào, lối ra ngay khi chọn bước (1 lần lưu)
- **1.13.** Gộp tiêu đề bộ trạng thái, số liệu và tab xem vào một hàng (1 lần lưu)
- **1.14.** Thu gọn sidebar bộ trạng thái, mở chi tiết bước bằng drawer, và đổi tab thành dải người phụ trách từng bước (1 lần lưu)
- **1.15.** Gọn drawer chi tiết bước, thu tóm tắt bộ vào mục đóng mở, thêm xóa bộ trạng thái, và bỏ nút sao chép link giới thiệu trên đăng nhập (1 lần lưu)
- **1.16.** Thêm nút sửa bộ trạng thái để đổi tên, mã và mô tả ngay trên bộ đang chọn (1 lần lưu)
- **1.17.** Tách sơ đồ và người phụ trách theo hướng thành công hoặc hủy, và chỉ thêm trạng thái từ menu của bộ đã chọn (2 lần lưu)

### 28/10/2026

- **1.1.** Mở rộng chat nhân viên (nhóm, poll, đính kèm, read receipt) và story social với sticker VA (3 lần lưu)
- **1.2.** Sửa tiếp phần Chấm công (chưa ghi câu mô tả) (1 lần lưu)
- **1.3.** Sửa tiếp phần Chat (chưa ghi câu mô tả) (1 lần lưu)
- **1.4.** Sửa tiếp phần Bảng tin (chưa ghi câu mô tả) (1 lần lưu)
- **1.5.** Sửa tiếp phần Chat (chưa ghi câu mô tả) (2 lần lưu)
- **1.6.** Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) (1 lần lưu)
- **1.7.** Sửa tiếp phần Bảng tin (chưa ghi câu mô tả) (1 lần lưu)
- **1.8.** Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) (1 lần lưu)
- **1.9.** Sửa tiếp phần Chat (chưa ghi câu mô tả) (7 lần lưu)
- **1.10.** Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) (1 lần lưu)
- **1.11.** Sửa tiếp phần Chat (chưa ghi câu mô tả) (3 lần lưu)
- **1.12.** Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) (2 lần lưu)
- **1.13.** Sửa tiếp phần Chat (chưa ghi câu mô tả) (4 lần lưu)
- **1.14.** Củng cố PWA, service worker và Web Push cho khu vực nhân viên (1 lần lưu)

### 29/10/2026

- **1.1.** Bổ sung tab bài đã duyệt và nâng cấp trang duyệt bài Social (1 lần lưu)
- **1.2.** Chuông thông báo shell mobile nhân viên và cải thiện lịch sử chấm công (1 lần lưu)
- **1.3.** Sửa tiếp phần Chat (chưa ghi câu mô tả) (3 lần lưu)
- **1.4.** Sửa tiếp phần Giao diện gốc (chưa ghi câu mô tả) (3 lần lưu)
- **1.5.** Sửa tiếp phần Chấm công (chưa ghi câu mô tả) (9 lần lưu)

### 30/10/2026

- **1.1.** Xuất PDF đơn nghỉ phép đã duyệt theo mẫu BM.01 (1 lần lưu)
- **1.2.** Giữ cơ sở hồ sơ khi chấm công, chỉ gửi chi nhánh khi nhân viên chọn cơ sở khác (1 lần lưu)
- **1.3.** Chỉnh mẫu PDF đơn nghỉ phép có quốc hiệu, chỗ ký và watermark (1 lần lưu)
- **1.4.** Ô soạn không render lại danh sách tin, kéo tin sát bàn phím (1 lần lưu)
- **1.5.** Khung gợi ý nhắc tên neo theo ô soạn, không bị thẻ bài cắt (1 lần lưu)
- **1.6.** Nhận link YouTube dạng shorts, live và nocookie, gắn origin lúc phát để khỏi lỗi 153 (1 lần lưu)
- **1.7.** Chuyển tài liệu module sang bố cục BMAD và thêm rule đối chiếu phiếu (1 lần lưu)
- **1.8.** Phiếu release và audit ngày 30/10 (1 lần lưu)
- **1.9.** Implement colleague tagging feature with mentions support (1 lần lưu)
- **1.10.** Nhóm bảng tin theo pháp nhân VM, Viễn Đông, Hoàng Việt và CIT (1 lần lưu)
- **1.11.** Đẩy yêu cầu sửa hồ sơ sang hàng đợi HCNS (1 lần lưu)
- **1.12.** Phiếu release và audit ngày 30/10 (1 lần lưu)
- **1.13.** Unify 'hired_at' and 'actual_start_date' fields in employee profile (1 lần lưu)

### 31/10/2026

- **1.1.** Giữ UUID khi trùng email, ẩn menu đơn từ chưa mở và giải thích xuất PDF (1 lần lưu)
- **1.2.** Phieu release va audit ngay 31/10 (1 lần lưu)

[← Mục lục](../README.md)

