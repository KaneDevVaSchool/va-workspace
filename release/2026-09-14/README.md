# 14/09/2026 — VA Workspace

Hôm nay có **15 lần lưu**, gom thành **12 việc**.

Phiếu tuần: [2026-W38](../summary/2026-W38-summary.md) · Đối soát: [audit.md](./audit.md) · [Mục lục](../README.md)

## Nhìn nhanh

1. **Tổng quan.** Màn: Quyền và nhật ký, Công việc và dự án, Cấu hình đơn từ, Hồ sơ đăng nhập, Tổng quan của tôi, Cấu hình menu. Seed nhân sự CNTT, xuất Excel Credential, quản lý task nhanh, cấu hình menu sidebar (1 lần lưu)
2. **Dự án và công việc.** Màn: Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập. Drop đúng tên FK tường minh khi rollback 3 migration (2 lần lưu)
3. **Đánh giá.** Sửa tiếp phần Đánh giá (chưa ghi câu mô tả) (2 lần lưu)
4. **Đánh giá.** Drop bảng pivot mồ côi trước khi rollback evaluation_criteria/positions (1 lần lưu)
5. **Nền tảng.** Thu hẹp DatabaseSeeder chỉ còn quyền + nhân sự (1 lần lưu)
6. **Định danh và phân quyền.** Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) (1 lần lưu)
7. **Tổng quan.** Màn: Bảng tin, Công việc và dự án, Tổng quan của tôi. Sửa tiếp phần Tổng quan (chưa ghi câu mô tả) (1 lần lưu)
8. **Dự án và công việc.** Màn: Công việc và dự án. Sửa tiếp phần Dự án và công việc (chưa ghi câu mô tả) (1 lần lưu)
9. **Định danh và phân quyền.** Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả) (1 lần lưu)
10. **Nền tảng.** Sửa tiếp phần Nền tảng (chưa ghi câu mô tả) (2 lần lưu)
11. **Tổng quan.** Màn: Công việc và dự án, Tổng quan của tôi, Cấu hình menu. Sửa tiếp phần Tổng quan (chưa ghi câu mô tả) (1 lần lưu)
12. **Dự án và công việc.** Màn: Công việc và dự án. Updates task tiến độ (1 lần lưu)

## Từng việc

### 1. Seed nhân sự CNTT, xuất Excel Credential, quản lý task nhanh, cấu hình menu sidebar

- **Phần:** Tổng quan
- **Màn hình:** Quyền và nhật ký, Công việc và dự án, Cấu hình đơn từ, Hồ sơ đăng nhập, Tổng quan của tôi, Cấu hình menu
- **Lần lưu:** 1
- **Đổi dữ liệu:** Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu

Các lần lưu, từ cũ đến mới:

- `1d1d158` — Seed nhân sự CNTT, xuất Excel Credential, quản lý task nhanh, cấu hình menu sidebar

### 2. Drop đúng tên FK tường minh khi rollback 3 migration

- **Phần:** Dự án và công việc
- **Màn hình:** Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập
- **Lần lưu:** 2
- **Đổi dữ liệu:** Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu; Đổi bảng công việc

Các lần lưu, từ cũ đến mới:

- `44ea7ae` — Drop đúng tên FK tường minh khi rollback 3 migration
- `225b03b` — Sửa tiếp cùng việc. Màn: Quyền và nhật ký, Công việc và dự án, Hồ sơ đăng nhập.

### 3. Sửa tiếp phần Đánh giá (chưa ghi câu mô tả)

- **Phần:** Đánh giá
- **Màn hình:** Không gắn một màn hình cụ thể
- **Lần lưu:** 2
- **Đổi dữ liệu:** Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu

Các lần lưu, từ cũ đến mới:

- `704e310` — Sửa tiếp cùng việc.
- `c0a4bf4` — Sửa tiếp cùng việc.

### 4. Drop bảng pivot mồ côi trước khi rollback evaluation_criteria/positions

- **Phần:** Đánh giá
- **Màn hình:** Không gắn một màn hình cụ thể
- **Lần lưu:** 1
- **Đổi dữ liệu:** Đổi cấu trúc dữ liệu; Đổi cấu trúc dữ liệu

Các lần lưu, từ cũ đến mới:

- `edbd53c` — Drop bảng pivot mồ côi trước khi rollback evaluation_criteria/positions

### 5. Thu hẹp DatabaseSeeder chỉ còn quyền + nhân sự

- **Phần:** Nền tảng
- **Màn hình:** Không gắn một màn hình cụ thể
- **Lần lưu:** 1
- **Đổi dữ liệu:** Không

Các lần lưu, từ cũ đến mới:

- `cbb4a1b` — Thu hẹp DatabaseSeeder chỉ còn quyền + nhân sự

### 6. Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả)

- **Phần:** Định danh và phân quyền
- **Màn hình:** Không gắn một màn hình cụ thể
- **Lần lưu:** 1
- **Đổi dữ liệu:** Không

Các lần lưu, từ cũ đến mới:

- `17f3e5a` — Sửa tiếp cùng việc.

### 7. Sửa tiếp phần Tổng quan (chưa ghi câu mô tả)

- **Phần:** Tổng quan
- **Màn hình:** Bảng tin, Công việc và dự án, Tổng quan của tôi
- **Lần lưu:** 1
- **Đổi dữ liệu:** Không

Các lần lưu, từ cũ đến mới:

- `0ab86f5` — Sửa tiếp cùng việc. Màn: Bảng tin, Công việc và dự án, Tổng quan của tôi.

### 8. Sửa tiếp phần Dự án và công việc (chưa ghi câu mô tả)

- **Phần:** Dự án và công việc
- **Màn hình:** Công việc và dự án
- **Lần lưu:** 1
- **Đổi dữ liệu:** Không

Các lần lưu, từ cũ đến mới:

- `94a53ba` — Sửa tiếp cùng việc. Màn: Công việc và dự án.

### 9. Sửa tiếp phần Định danh và phân quyền (chưa ghi câu mô tả)

- **Phần:** Định danh và phân quyền
- **Màn hình:** Không gắn một màn hình cụ thể
- **Lần lưu:** 1
- **Đổi dữ liệu:** Không

Các lần lưu, từ cũ đến mới:

- `384bd96` — Sửa tiếp cùng việc.

### 10. Sửa tiếp phần Nền tảng (chưa ghi câu mô tả)

- **Phần:** Nền tảng
- **Màn hình:** Không gắn một màn hình cụ thể
- **Lần lưu:** 2
- **Đổi dữ liệu:** Không

Các lần lưu, từ cũ đến mới:

- `d7da3d2` — Sửa tiếp cùng việc.
- `955a6f1` — Sửa tiếp cùng việc.

### 11. Sửa tiếp phần Tổng quan (chưa ghi câu mô tả)

- **Phần:** Tổng quan
- **Màn hình:** Công việc và dự án, Tổng quan của tôi, Cấu hình menu
- **Lần lưu:** 1
- **Đổi dữ liệu:** Đổi cấu trúc dữ liệu

Các lần lưu, từ cũ đến mới:

- `02ab869` — Sửa tiếp cùng việc. Màn: Công việc và dự án, Tổng quan của tôi, Cấu hình menu.

### 12. Updates task tiến độ

- **Phần:** Dự án và công việc
- **Màn hình:** Công việc và dự án
- **Lần lưu:** 1
- **Đổi dữ liệu:** Không

Các lần lưu, từ cũ đến mới:

- `5d4cd55` — Updates task tiến độ

## Khi đưa lên máy chủ

Ngày này có đổi dữ liệu. Cập nhật dữ liệu trước khi dùng:

- Đổi cấu trúc dữ liệu
- Đổi cấu trúc dữ liệu
- Đổi cấu trúc dữ liệu
- Đổi cấu trúc dữ liệu
- Đổi cấu trúc dữ liệu
- Đổi cấu trúc dữ liệu
- Đổi bảng công việc
- Đổi cấu trúc dữ liệu
- Đổi cấu trúc dữ liệu
- Đổi cấu trúc dữ liệu
- Đổi cấu trúc dữ liệu

