<?php

namespace App\Services\Limo;

use Illuminate\Support\Str;
use Throwable;

/**
 * Builds temporary processed image variants for plate OCR (GD only). Caller must delete temp_dir when done.
 */
class LimoPlateImagePreprocessor
{
    private const OCR_UPSCALE_MAX_EDGE = 3200;

    private const OCR_UPSCALE_MAX_PIXELS = 25_000_000;

    /**
     * @return array{variants: list<array{name: string, path: string}>, temp_dir: ?string}
     */
    public function buildVariants(string $absoluteOriginalPath): array
    {
        if (! is_file($absoluteOriginalPath) || ! is_readable($absoluteOriginalPath)) {
            return ['variants' => [['name' => 'original', 'path' => $absoluteOriginalPath]], 'temp_dir' => null];
        }

        if (! extension_loaded('gd')) {
            return ['variants' => [['name' => 'original', 'path' => $absoluteOriginalPath]], 'temp_dir' => null];
        }

        $tempDir = storage_path('framework/cache/limo_ocr/'.Str::random(20));
        if (! @mkdir($tempDir, 0750, true) && ! is_dir($tempDir)) {
            return ['variants' => [['name' => 'original', 'path' => $absoluteOriginalPath]], 'temp_dir' => null];
        }

        try {
            $binary = @file_get_contents($absoluteOriginalPath);
            if ($binary === false || $binary === '') {
                self::rrmdir($tempDir);

                return ['variants' => [['name' => 'original', 'path' => $absoluteOriginalPath]], 'temp_dir' => null];
            }

            $src = @imagecreatefromstring($binary);
            if ($src === false) {
                self::rrmdir($tempDir);

                return ['variants' => [['name' => 'original', 'path' => $absoluteOriginalPath]], 'temp_dir' => null];
            }

            $variants = [];

            $pathOriginal = $tempDir.'/a_original.png';
            if (@imagepng($src, $pathOriginal)) {
                $variants[] = ['name' => 'original', 'path' => $pathOriginal];
            }

            $b = self::variantBContrastSharpen($src);
            if ($b !== false) {
                $pb = $tempDir.'/b_gray_contrast_sharpen.png';
                if (@imagepng($b, $pb)) {
                    $variants[] = ['name' => 'b_gray_contrast_sharpen', 'path' => $pb];
                }
                imagedestroy($b);
            }

            $c = self::variantCHighContrastBinary($src);
            if ($c !== false) {
                $pc = $tempDir.'/c_gray_threshold.png';
                if (@imagepng($c, $pc)) {
                    $variants[] = ['name' => 'c_gray_threshold', 'path' => $pc];
                }

                $cInv = self::invertBinaryImage($c);
                if ($cInv !== false) {
                    $pcInv = $tempDir.'/c_gray_threshold_inverted.png';
                    if (@imagepng($cInv, $pcInv)) {
                        $variants[] = ['name' => 'c_gray_threshold_inverted', 'path' => $pcInv];
                    }
                    imagedestroy($cInv);
                }

                $c2 = self::upscaleNearest($c, 2);
                if ($c2 !== false) {
                    $p2 = $tempDir.'/c_gray_threshold_scale2.png';
                    if (@imagepng($c2, $p2)) {
                        $variants[] = ['name' => 'c_gray_threshold_scale2', 'path' => $p2];
                    }
                    imagedestroy($c2);
                }

                $c3 = self::upscaleNearest($c, 3);
                if ($c3 !== false) {
                    $p3 = $tempDir.'/c_gray_threshold_scale3.png';
                    if (@imagepng($c3, $p3)) {
                        $variants[] = ['name' => 'c_gray_threshold_scale3', 'path' => $p3];
                    }
                    imagedestroy($c3);
                }

                imagedestroy($c);
            }

            $d = self::variantDCenterBandThreshold($src);
            if ($d !== false) {
                $pd = $tempDir.'/d_center_band_threshold.png';
                if (@imagepng($d, $pd)) {
                    $variants[] = ['name' => 'd_center_band_threshold', 'path' => $pd];
                }
                imagedestroy($d);
            }

            $e = self::variantVerticalBandThreshold($src, 0.25, 0.75);
            if ($e !== false) {
                $pe = $tempDir.'/e_crop_center_half_height.png';
                if (@imagepng($e, $pe)) {
                    $variants[] = ['name' => 'e_crop_center_half_height', 'path' => $pe];
                }
                imagedestroy($e);
            }

            $f = self::variantVerticalBandThreshold($src, 0.5, 1.0);
            if ($f !== false) {
                $pf = $tempDir.'/f_crop_lower_half_height.png';
                if (@imagepng($f, $pf)) {
                    $variants[] = ['name' => 'f_crop_lower_half_height', 'path' => $pf];
                }
                imagedestroy($f);
            }

            $g = self::variantVerticalBandThreshold($src, 0.12, 0.52);
            if ($g !== false) {
                $pg = $tempDir.'/g_crop_upper_mid_40pct_height.png';
                if (@imagepng($g, $pg)) {
                    $variants[] = ['name' => 'g_crop_upper_mid_40pct_height', 'path' => $pg];
                }

                $gInv = self::invertBinaryImage($g);
                if ($gInv !== false) {
                    $pgInv = $tempDir.'/g_crop_upper_mid_40pct_height_inverted.png';
                    if (@imagepng($gInv, $pgInv)) {
                        $variants[] = ['name' => 'g_crop_upper_mid_40pct_height_inverted', 'path' => $pgInv];
                    }
                    imagedestroy($gInv);
                }

                $g2 = self::upscaleNearest($g, 2);
                if ($g2 !== false) {
                    $pg2 = $tempDir.'/g_crop_upper_mid_40pct_height_scale2.png';
                    if (@imagepng($g2, $pg2)) {
                        $variants[] = ['name' => 'g_crop_upper_mid_40pct_height_scale2', 'path' => $pg2];
                    }
                    imagedestroy($g2);
                }

                $g3 = self::upscaleNearest($g, 3);
                if ($g3 !== false) {
                    $pg3 = $tempDir.'/g_crop_upper_mid_40pct_height_scale3.png';
                    if (@imagepng($g3, $pg3)) {
                        $variants[] = ['name' => 'g_crop_upper_mid_40pct_height_scale3', 'path' => $pg3];
                    }
                    imagedestroy($g3);
                }

                imagedestroy($g);
            }

            imagedestroy($src);

            if ($variants === []) {
                self::rrmdir($tempDir);

                return ['variants' => [['name' => 'original', 'path' => $absoluteOriginalPath]], 'temp_dir' => null];
            }

            return ['variants' => $variants, 'temp_dir' => $tempDir];
        } catch (Throwable) {
            self::rrmdir($tempDir);

            return ['variants' => [['name' => 'original', 'path' => $absoluteOriginalPath]], 'temp_dir' => null];
        }
    }

    public static function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                self::rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /** @return \GdImage|false */
    private static function toGrayscale(\GdImage $src): \GdImage|false
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $gray = imagecreatetruecolor($w, $h);
        if ($gray === false) {
            return false;
        }
        imagealphablending($gray, false);
        imagesavealpha($gray, true);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($src, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $l = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $c = imagecolorallocate($gray, $l, $l, $l);
                imagesetpixel($gray, $x, $y, $c);
            }
        }

        return $gray;
    }

    /** @return \GdImage|false */
    private static function variantBContrastSharpen(\GdImage $src): \GdImage|false
    {
        $g = self::toGrayscale($src);
        if ($g === false) {
            return false;
        }
        imagefilter($g, IMG_FILTER_CONTRAST, -35);
        $sharpen = [[-1, -1, -1], [-1, 16, -1], [-1, -1, -1]];
        imageconvolution($g, $sharpen, 8, 0);

        return $g;
    }

    /** @return \GdImage|false */
    private static function variantCHighContrastBinary(\GdImage $src): \GdImage|false
    {
        $g = self::toGrayscale($src);
        if ($g === false) {
            return false;
        }
        imagefilter($g, IMG_FILTER_CONTRAST, -55);
        $w = imagesx($g);
        $h = imagesy($g);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($g, $x, $y);
                $l = $rgb & 0xFF;
                $v = $l < 128 ? 0 : 255;
                $c = imagecolorallocate($g, $v, $v, $v);
                imagesetpixel($g, $x, $y, $c);
            }
        }

        return $g;
    }

    /**
     * Vertical crop (relative rows y0..y1), then high-contrast binary — plate-focused bands without OpenCV.
     *
     * @return \GdImage|false
     */
    private static function variantVerticalBandThreshold(\GdImage $src, float $y0r, float $y1r): \GdImage|false
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 2 || $h < 4) {
            return false;
        }
        $y0 = (int) max(0, floor($h * min($y0r, $y1r)));
        $y1 = (int) min($h, max($y0 + 1, (int) ceil($h * max($y0r, $y1r))));
        $ch = $y1 - $y0;
        if ($ch < 4) {
            return false;
        }
        $band = imagecreatetruecolor($w, $ch);
        if ($band === false) {
            return false;
        }
        imagecopy($band, $src, 0, 0, 0, $y0, $w, $ch);
        $out = self::variantCHighContrastBinary($band);
        imagedestroy($band);

        return $out;
    }

    /** @return \GdImage|false */
    private static function invertBinaryImage(\GdImage $binary): \GdImage|false
    {
        $w = imagesx($binary);
        $h = imagesy($binary);
        if ($w < 1 || $h < 1) {
            return false;
        }
        $out = imagecreatetruecolor($w, $h);
        if ($out === false) {
            return false;
        }
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($binary, $x, $y);
                $l = $rgb & 0xFF;
                $nv = 255 - $l;
                $c = imagecolorallocate($out, $nv, $nv, $nv);
                imagesetpixel($out, $x, $y, $c);
            }
        }

        return $out;
    }

    /**
     * Nearest-neighbour upscale for small plate crops (GD only).
     *
     * @return \GdImage|false
     */
    private static function upscaleNearest(\GdImage $src, int $factor): \GdImage|false
    {
        if ($factor < 2) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            return false;
        }
        $nw = $w * $factor;
        $nh = $h * $factor;
        if ($nw > self::OCR_UPSCALE_MAX_EDGE || $nh > self::OCR_UPSCALE_MAX_EDGE) {
            return false;
        }
        if ($nw * $nh > self::OCR_UPSCALE_MAX_PIXELS) {
            return false;
        }
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            return false;
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    /** @return \GdImage|false */
    private static function variantDCenterBandThreshold(\GdImage $src): \GdImage|false
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 2 || $h < 8) {
            return false;
        }
        $y0 = (int) floor($h * 0.32);
        $ch = max(8, (int) floor($h * 0.36));
        if ($y0 + $ch > $h) {
            $ch = $h - $y0;
        }
        if ($ch < 8) {
            return false;
        }
        $band = imagecreatetruecolor($w, $ch);
        if ($band === false) {
            return false;
        }
        imagecopy($band, $src, 0, 0, 0, $y0, $w, $ch);
        $out = self::variantCHighContrastBinary($band);
        imagedestroy($band);

        return $out;
    }
}
