#!/usr/bin/env python3
"""Sinh apple-touch-startup-image PNG cho iOS (khớp config/pwa_ios.php)."""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "public" / "images" / "pwa" / "ios-splash"
LOGO = ROOT / "public" / "images" / "logo-2.png"
WATERMARK = ROOT / "public" / "images" / "background" / "background-logo.png"

BRAND_BG = (0x9A, 0x00, 0x36)
BRAND_TEXT = (0xFF, 0xFF, 0xFF)

# Kích thước pixel portrait (WxH) — trùng tên file config/pwa_ios.php
SIZES: list[tuple[int, int]] = [
    (750, 1334),
    (1125, 2436),
    (1170, 2532),
    (1179, 2556),
    (1206, 2622),
    (1284, 2778),
    (1290, 2796),
    (1320, 2868),
    (1536, 2048),
    (1668, 2388),
    (2048, 2732),
]


def _paste_contain(base: Image.Image, overlay: Image.Image, *, max_width_ratio: float, opacity: float) -> None:
    if not overlay.mode == "RGBA":
        overlay = overlay.convert("RGBA")
    w, h = base.size
    max_w = int(w * max_width_ratio)
    scale = min(max_w / overlay.width, (h * 0.35) / overlay.height, 1.0)
    nw, nh = max(1, int(overlay.width * scale)), max(1, int(overlay.height * scale))
    resized = overlay.resize((nw, nh), Image.Resampling.LANCZOS)
    if opacity < 1:
        alpha = resized.split()[3]
        alpha = alpha.point(lambda p: int(p * opacity))
        resized.putalpha(alpha)
    x = (w - nw) // 2
    y = int(h * 0.38) - nh // 2
    base.paste(resized, (x, y), resized)


def _draw_brand_label(canvas: Image.Image) -> None:
    w, h = canvas.size
    draw = ImageDraw.Draw(canvas)
    label = "VA Workspace"
    font_size = max(18, w // 22)
    try:
        font = ImageFont.truetype("arial.ttf", font_size)
    except OSError:
        font = ImageFont.load_default()
    bbox = draw.textbbox((0, 0), label, font=font)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    draw.text(((w - tw) // 2, int(h * 0.38) + h // 12), label, fill=BRAND_TEXT, font=font)


def render_splash(width: int, height: int, logo: Image.Image, watermark: Image.Image | None) -> Image.Image:
    canvas = Image.new("RGB", (width, height), BRAND_BG)
    if watermark is not None:
        _paste_contain(canvas, watermark, max_width_ratio=0.85, opacity=0.12)
    _paste_contain(canvas, logo, max_width_ratio=0.55, opacity=1.0)
    _draw_brand_label(canvas)
    return canvas


def main() -> None:
    if not LOGO.is_file():
        raise SystemExit(f"Thiếu logo: {LOGO}")

    logo = Image.open(LOGO).convert("RGBA")
    wm = Image.open(WATERMARK).convert("RGBA") if WATERMARK.is_file() else None
    OUT.mkdir(parents=True, exist_ok=True)

    for width, height in SIZES:
        path = OUT / f"{width}x{height}.png"
        img = render_splash(width, height, logo, wm)
        img.save(path, optimize=True)
        print("Wrote", path.relative_to(ROOT))

    print("Done. Bump PWA_SPLASH_VERSION if Safari caches old splash.")


if __name__ == "__main__":
    main()
