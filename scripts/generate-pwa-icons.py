#!/usr/bin/env python3
"""Sinh favicon + icon PWA từ public/images/pwa/mascot-logo-source.png."""

from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "public" / "images" / "pwa" / "mascot-logo-source.png"
IMAGES = ROOT / "public" / "images"
PWA = IMAGES / "pwa"

# Nền trắng khớp artwork nguồn; maskable thu nhỏ ~76% để nằm trong vùng an toàn Android.
MASKABLE_CONTENT_SCALE = 0.76
ICON_CONTENT_SCALE = 1.0


def _square_canvas(
    source: Image.Image,
    size: int,
    *,
    bg: tuple[int, int, int] = (255, 255, 255),
    content_scale: float = 1.0,
) -> Image.Image:
    src = source.convert("RGBA")
    w, h = src.size
    scale = min(size / w, size / h) * content_scale
    nw, nh = max(1, int(w * scale)), max(1, int(h * scale))
    resized = src.resize((nw, nh), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (size, size), bg + (255,))
    canvas.paste(resized, ((size - nw) // 2, (size - nh) // 2), resized)
    return canvas


def _save_rgb(path: Path, image: Image.Image) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    image.convert("RGB").save(path, optimize=True)


def main() -> None:
    if not SOURCE.is_file():
        raise SystemExit(f"Thiếu file nguồn: {SOURCE}")

    source = Image.open(SOURCE)

    for size, name in ((192, "icon-192.png"), (512, "icon-512.png")):
        _save_rgb(PWA / name, _square_canvas(source, size, content_scale=ICON_CONTENT_SCALE))

    for size, name in ((192, "maskable-192.png"), (512, "maskable-512.png")):
        _save_rgb(
            PWA / name,
            _square_canvas(source, size, content_scale=MASKABLE_CONTENT_SCALE),
        )

    # iOS Safari: “Thêm vào Màn hình chính” lấy apple-touch-icon (180×180).
    _save_rgb(PWA / "apple-touch-icon.png", _square_canvas(source, 180, content_scale=ICON_CONTENT_SCALE))

    fav32 = _square_canvas(source, 32, content_scale=ICON_CONTENT_SCALE)
    _save_rgb(IMAGES / "favicon.png", fav32)
    _save_rgb(IMAGES / "favicon-2.png", fav32)

    ico_layers = [_square_canvas(source, s, content_scale=ICON_CONTENT_SCALE) for s in (16, 32, 48)]
    ico_layers[0].save(
        ROOT / "public" / "favicon.ico",
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48)],
    )

    print("Generated PWA icons from", SOURCE.name)


if __name__ == "__main__":
    main()
