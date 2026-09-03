<?php

namespace Modules\Evaluation\App\Support;

/**
 * Dấu chìm họa tiết VA trên PDF (DomPDF).
 *
 * Ảnh nguồn `background-logo.png` là rồng trắng rất mờ (alpha ~5%, dùng cho
 * nền brand tối). Ở giấy trắng: lấy hình theo độ sáng, nhuộm primary-900,
 * alpha nướng sẵn — DomPDF không luôn áp được set_opacity lên ảnh.
 */
class PdfWatermark
{
    /**
     * @param  \Barryvdh\DomPDF\PDF  $pdf
     */
    public function stamp($pdf): void
    {
        $made = $this->makePng();
        if ($made === null) {
            return;
        }

        try {
            $this->apply($pdf, $made['path'], $made['width'], $made['height']);
        } finally {
            if (is_file($made['path'])) {
                @unlink($made['path']);
            }
        }
    }

    /**
     * @param  \Barryvdh\DomPDF\PDF  $pdf
     */
    private function apply($pdf, string $watermarkPath, int $imgW, int $imgH): void
    {
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $pageW = $canvas->get_width();
        $pageH = $canvas->get_height();

        $maxW = $pageW * 0.62;
        $maxH = $pageH * 0.62;
        $scale = min($maxW / max(1, $imgW), $maxH / max(1, $imgH));
        $w = $imgW * $scale;
        $h = $imgH * $scale;
        $x = ($pageW - $w) / 2;
        $y = ($pageH - $h) / 2;

        $canvas->page_script(function ($pageNumber, $pageCount, $canvas) use ($watermarkPath, $x, $y, $w, $h) {
            $canvas->image($watermarkPath, $x, $y, $w, $h);
        });
    }

    /**
     * @return array{path: string, width: int, height: int}|null
     */
    private function makePng(): ?array
    {
        $srcPath = public_path('images/background/background-logo.png');
        if (! is_file($srcPath) || ! function_exists('imagecreatefrompng')) {
            return null;
        }

        $src = @imagecreatefrompng($srcPath);
        if ($src === false) {
            return null;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $width, $height, $clear);

        $brandR = 0x9A;
        $brandG = 0x00;
        $brandB = 0x36;
        $fade = 0.28;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($src, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $lum = ($r + $g + $b) / 765;
                if ($lum < 0.08) {
                    continue;
                }
                $opacity = min(1, $lum) * $fade;
                $newAlpha = (int) round(127 * (1 - $opacity));
                $newAlpha = max(0, min(127, $newAlpha));
                $color = imagecolorallocatealpha($dst, $brandR, $brandG, $brandB, $newAlpha);
                imagesetpixel($dst, $x, $y, $color);
            }
        }

        imagedestroy($src);

        $tmp = tempnam(sys_get_temp_dir(), 'vas-wm-');
        if ($tmp === false) {
            imagedestroy($dst);

            return null;
        }
        $pngPath = $tmp.'.png';
        @unlink($tmp);
        $ok = imagepng($dst, $pngPath);
        imagedestroy($dst);

        if (! $ok || ! is_file($pngPath)) {
            return null;
        }

        return ['path' => $pngPath, 'width' => $width, 'height' => $height];
    }
}
