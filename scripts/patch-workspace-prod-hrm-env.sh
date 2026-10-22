#!/usr/bin/env bash
# Patch HRM_* trên .env Workspace production (chạy trên VPS với sudo).
#   ssh -t khoana-trienlamtranh 'bash ~/patch-workspace-prod-hrm-env.sh'
set -euo pipefail

APP=/home/vaschools-workspace/htdocs/workspace.vaschools.edu.vn
ENV="$APP/.env"
USER=vaschools-workspace

if ! sudo test -f "$ENV"; then
  echo "Không thấy $ENV — kiểm tra đường dẫn CloudPanel."
  exit 1
fi

BK="$ENV.bak.$(date +%Y%m%d_%H%M%S)"
sudo cp "$ENV" "$BK"
echo "Backup: $BK"

sudo python3 - "$ENV" <<'PY'
import pathlib
import re
import sys

path = pathlib.Path(sys.argv[1])
text = path.read_text(encoding="utf-8")

updates = {
    "HRM_API_BASE_URL": "https://hrm.vaschools.edu.vn",
    "HRM_API_TOKEN": "3|ADWNg18R8wRE94yCVfc3OFGcqy5QrKRoe0G2XgQa726a7d86",
    "HRM_TIMESHEET_API_TOKEN": "3|ADWNg18R8wRE94yCVfc3OFGcqy5QrKRoe0G2XgQa726a7d86",
    "HRM_SSO_CLIENT_ID": "va-workspace",
    "HRM_SSO_ISSUER": "https://hrm.vaschools.edu.vn",
    "HRM_SSO_CALLBACK_URL": "https://workspace.vaschools.edu.vn/auth/hrm/callback",
}

for key in list(updates):
    pat = re.compile(rf"^{re.escape(key)}=.*$", re.M)
    if pat.search(text):
        text = pat.sub(f"{key}={updates[key]}", text, count=1)
    else:
        text = text.rstrip() + f"\n{key}={updates[key]}\n"

for leave_key in ("HRM_LEAVE_API_BASE_URL", "HRM_LEAVE_API_TOKEN"):
    text = re.sub(rf"^{re.escape(leave_key)}=.*\n?", "", text, flags=re.M)

path.write_text(text, encoding="utf-8")
print("Patched HRM_* (đã gỡ HRM_LEAVE_API_* nếu có).")
PY

# .env prod hay có block HRM_* trùng ở cuối file — Laravel lấy dòng sau cùng.
python3 - "$ENV" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
lines = p.read_text(encoding="utf-8").splitlines()
seen = set()
out = []
for line in lines:
    if "=" in line and not line.lstrip().startswith("#"):
        key = line.split("=", 1)[0].strip()
        if key in (
            "HRM_API_TOKEN",
            "HRM_TIMESHEET_API_TOKEN",
            "HRM_WEBHOOK_SECRET",
            "HRM_LEAVE_API_BASE_URL",
            "HRM_LEAVE_API_TOKEN",
        ):
            if key in seen:
                continue
            seen.add(key)
    out.append(line)
p.write_text("\n".join(out) + "\n", encoding="utf-8")
PY

sudo chown "$USER:$USER" "$ENV"
sudo -u "$USER" bash -lc "cd '$APP' && php artisan config:clear && php artisan config:cache"
sudo -u "$USER" bash -lc "cd '$APP' && php artisan identity:hrm-timesheet-diagnose" || true

echo "Done."
