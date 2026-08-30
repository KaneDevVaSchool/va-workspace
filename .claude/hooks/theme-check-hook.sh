#!/usr/bin/env bash
# PostToolUse hook (Write|Edit) — cảnh báo vi phạm .claude/CLAUDE.md mục 2
# (border theo từng hướng bị cấm) và mục 12 (hex hard-code) ngay sau khi
# Claude vừa ghi/sửa file .vue hoặc .css. Không chặn (luôn exit 0) — chỉ in
# cảnh báo ra stderr để Claude thấy trong tool result và tự sửa ngay.
set -u

payload="$(cat)"

# Lấy file_path từ JSON stdin không cần jq — field nằm trong tool_input.
file_path="$(printf '%s' "$payload" | grep -o '"file_path"[[:space:]]*:[[:space:]]*"[^"]*"' | head -1 | sed -E 's/.*:[[:space:]]*"//; s/"$//')"

[ -z "$file_path" ] && exit 0

case "$file_path" in
  *.vue|*.css) ;;
  *) exit 0 ;;
esac

[ -f "$file_path" ] || exit 0

# theme.css là nguồn định nghĩa hex hợp lệ — không tự cảnh báo chính nó.
is_theme_file=0
case "$file_path" in
  *resources/css/theme.css) is_theme_file=1 ;;
esac

warned=0

border_hits="$(grep -nE 'border-(left|right|top|bottom)(-color)?[[:space:]]*:' "$file_path" 2>/dev/null)"
if [ -n "$border_hits" ]; then
  warned=1
  echo "⚠️  [theme-check] Vi phạm CLAUDE.md mục 2 (cấm border theo từng hướng) trong $file_path:" >&2
  echo "$border_hits" | sed 's/^/    /' >&2
  echo "    → Sửa: border đủ 4 cạnh dùng shorthand 'border: <width> <style> var(--color-...)';" >&2
  echo "      viền 1 cạnh dùng 'box-shadow: 0 2px 0 var(--color-...)' (hoặc 1px 0 0 cho cạnh dọc) hoặc outline." >&2
fi

if [ "$is_theme_file" -eq 0 ]; then
  hex_hits="$(grep -nE '#[0-9a-fA-F]{3,8}\b' "$file_path" 2>/dev/null | grep -vE '^\s*[0-9]+:\s*(//|\*|/\*)')"
  if [ -n "$hex_hits" ]; then
    warned=1
    echo "⚠️  [theme-check] Vi phạm CLAUDE.md mục 12 (hex hard-code, phải dùng var(--color-...)) trong $file_path:" >&2
    echo "$hex_hits" | sed 's/^/    /' >&2
    echo "    → Sửa: thay bằng biến var(--color-...) tương ứng gần nhất trong resources/css/theme.css." >&2
  fi
fi

if [ "$warned" -eq 1 ]; then
  echo "    (Xem thêm skill theme-check / .claude/CLAUDE.md nếu cần đối chiếu đầy đủ.)" >&2
fi

exit 0
