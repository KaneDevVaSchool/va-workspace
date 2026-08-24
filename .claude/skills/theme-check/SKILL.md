---
name: theme-check
description: Rà soát code CSS/Vue vừa thêm hoặc sửa để phát hiện vi phạm quy tắc theme của dự án — border theo từng hướng bị cấm, màu/font hard-code thay vì dùng CSS var, thiếu responsive, title/tooltip/placeholder dùng làm hint, badge/pill trạng thái, ngôn ngữ kỹ thuật thay vì tiếng Việt phổ thông. Dùng khi người dùng yêu cầu "kiểm tra theme", "review CSS", "check quy tắc border", "check hint", "check UI đơn giản", hoặc trước khi hoàn thành một task có sửa file .css/.vue/.blade.php.
---

# Theme Check

Quét các file đã thay đổi (CSS, `.vue`, `.blade.php`) để tìm vi phạm quy
tắc trong `.claude/CLAUDE.md` mục 1, 6, 7, 8, 11, 12, 13, 14.

## Cách quét

1. Lấy danh sách file đã sửa (`git diff --name-only` nếu là git repo, hoặc
   danh sách file vừa chỉnh trong task hiện tại).

2. Trong các file `.css`, `<style>` của `.vue`, tìm các pattern bị cấm:
   - `border-left`, `border-right`, `border-top`, `border-bottom`
   - `border-left-color`, `border-right-color`, `border-top-color`, `border-bottom-color`
   - Tailwind (nếu dự án dùng): `border-l-`, `border-r-`, `border-t-`, `border-b-`

   Dùng grep, ví dụ:
   ```
   grep -rnE "border-(left|right|top|bottom)(-color)?\s*:" resources Modules --include="*.css" --include="*.vue"
   ```

3. Tìm mã màu hard-code (`#[0-9a-fA-F]{3,8}` hoặc `rgb(`/`rgba(`) nằm ngoài
   `resources/css/theme.css` — mọi màu khác nên tham chiếu `var(--color-...)`.

4. Tìm `font-family:` hard-code khác `var(--font-family-base)` ngoài `theme.css`/`app.css`.

5. Với file `.vue` có `<style>`, kiểm tra có ít nhất 1 media query
   (`@media`) khi component có layout phức tạp (grid/flex nhiều cột) —
   cảnh báo nếu thiếu, không bắt buộc tuyệt đối (một số component nhỏ có
   thể responsive tự nhiên qua flex-wrap).

6. Tìm hint/tooltip bị cấm theo mục 13:
   - Thuộc tính `title="..."` trên bất kỳ thẻ HTML/Vue nào (kể cả binding
     `:title="..."`) dùng làm tooltip — luôn là vi phạm, kể cả khi giá trị
     là biến động (ví dụ `:title="collapsed ? 'Mở rộng' : 'Thu gọn'"`).
   - `placeholder="..."` trên input/textarea mang nội dung là ý nghĩa
     chính của field (thay vì chỉ ví dụ định dạng) khi field đó không có
     `<label>` hiển thị sẵn đi kèm.

   Dùng grep, ví dụ:
   ```
   grep -rnE '\btitle\s*=\s*"' resources Modules --include="*.vue" --include="*.blade.php"
   grep -rnE ':title\s*=\s*"' resources Modules --include="*.vue"
   ```

   Lưu ý: `title="..."` là **prop** trên custom component (vd. `<PageHeader
   title="...">`) thì KHÔNG vi phạm — chỉ vi phạm khi là attribute HTML gốc
   trên thẻ chuẩn (`<button>`, `<a>`, `<span>`, `<div>`...) hoặc trên thẻ
   Vue mà `title` truyền thẳng xuống DOM (không khai báo trong `defineProps`
   của component đó).

7. Tìm badge/pill trạng thái bị cấm theo mục 14 (UI đơn giản, dễ hiểu):
   - Class/style dạng "viên thuốc" hiển thị trạng thái: nền màu bão hoà +
     `border-radius: var(--radius-full)` (hoặc bo tròn lớn tương đương) +
     padding ngang, bọc quanh 1-2 từ trạng thái (vd. "Được cấp", "Hoạt động",
     "Đã khoá"). Gợi ý sửa: thay bằng chữ thường + chấm màu nhỏ
     (`width/height: 0.5rem; border-radius: var(--radius-full)`) đặt trước
     chữ — xem `.perm-side__dot` trong `PermissionMatrix.vue` làm ví dụ.
   - Field/panel chi tiết dùng bố cục `<dl>`/grid 2 cột lệch trái-phải kiểu
     bảng biểu kỹ thuật thay vì mỗi field 1 dòng ngang
     `justify-content: space-between` — cảnh báo, gợi ý đổi sang dạng hàng
     ngang nhãn trái/giá trị phải, phân cách bằng `box-shadow` mảnh (không
     phải `border-bottom`).
   - Thuật ngữ kỹ thuật/tiếng Anh lộ ra trực tiếp trong UI (không phải code):
     "override", "scope", "effective_source", "reserved", tên biến/field
     kỹ thuật khác — nên có bản dịch câu tiếng Việt phổ thông thay thế.
   - Trạng thái rỗng (placeholder chờ dữ liệu) chiếm chỗ layout cố định khi
     chưa có gì để hiện — nên `v-if` để ẩn hẳn phần tử thay vì hiện khối rỗng.

## Báo cáo

Liệt kê từng vi phạm: `file:line` + đoạn code + gợi ý sửa (theo mục 1 của
`.claude/CLAUDE.md` — dùng `border` shorthand / `box-shadow` / `outline`,
hoặc thay hex bằng biến `var(--color-...)` tương ứng gần nhất trong
`resources/css/theme.css`; theo mục 13 — bỏ `title`, thay bằng label chữ
hiển thị sẵn hoặc `aria-label` nếu chỉ cần cho accessibility; theo mục 14 —
bỏ badge/pill, dùng chấm màu + chữ thường, đổi thuật ngữ kỹ thuật sang câu
tiếng Việt phổ thông, field ngay hàng).

Nếu không có vi phạm, báo ngắn gọn "Không phát hiện vi phạm quy tắc theme."
Không tự động sửa trừ khi người dùng yêu cầu — chỉ báo cáo.
