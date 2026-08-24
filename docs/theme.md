# Theme / Design Tokens

Nguồn sự thật: [`resources/css/theme.css`](../resources/css/theme.css). Không hard-code
mã màu hay font trong component — luôn dùng biến CSS bên dưới.

## Màu chính (Primary)

| Token | Mã màu |
|---|---|
| `--color-primary` (900) | `#9a0036` |
| `--color-primary-50` | `#ffeff1` |
| `--color-primary-100` | `#ffe0e4` |
| `--color-primary-200` | `#ffc5cf` |
| `--color-primary-300` | `#ff96a7` |
| `--color-primary-400` | `#ff5b7a` |
| `--color-primary-500` | `#ff2251` |
| `--color-primary-600` | `#ff0043` |
| `--color-primary-700` | `#da003f` |
| `--color-primary-800` | `#b6003a` |
| `--color-primary-900` | `#9a0036` |

## Font

`Gabarito` (Google Font), biến `--font-family-base`, nạp qua
`@import` trong [`resources/css/app.css`](../resources/css/app.css).

## Quy tắc border (BẮT BUỘC)

Không dùng `border-left`, `border-right`, `border-top`, `border-bottom`
hay `border-*-color` theo từng hướng. Dùng:
- `border` (shorthand đủ 4 cạnh), hoặc
- `box-shadow: 0 Npx 0 var(--color-...)` để giả lập 1 cạnh, hoặc
- `outline` khi cần viền không chiếm layout.

## Responsive

Breakpoint tham chiếu (dùng trực tiếp trong media query, biến `--bp-*`
chỉ mang tính tài liệu vì CSS không cho phép `var()` trong media query):
- Mobile: `max-width: 480px`
- Tablet: `max-width: 768px`
- Desktop: `min-width: 1280px`

## Scroll

Ưu tiên scroll trong container nội bộ (`overflow-y: auto` trên vùng nội
dung, không phải `body`), thanh scroll mỏng theo `--scrollbar-size` đã
định nghĩa sẵn trong `app.css`.
