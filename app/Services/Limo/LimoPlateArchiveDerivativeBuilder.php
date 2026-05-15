<?php

namespace App\Services\Limo;

use Illuminate\Support\Str;

/**
 * Builds a JPEG archival derivative for consumed limo plate uploads (MEGA only).
 * Does not affect live OCR or original upload storage.
 */
final class LimoPlateArchiveDerivativeBuilder
{
    public const MAX_LONG_EDGE_PX = 1600;

    public const JPEG_QUALITY = 80;

    /** Margin around user crop, as fraction of crop width/height (10–15% target). */
    public const CROP_MARGIN_FRACTION = 0.125;

    /**
     * @param  array{left:int,top:int,width:int,height:int}|null  $cropBasisPoints
     */
    public function buildForArchive(string $absoluteOriginalPath, ?array $cropBasisPoints = null): ?ArchiveDerivativeResult
    {
        if (! extension_loaded('gd')) {
            return null;
        }
        if (! is_file($absoluteOriginalPath) || ! is_readable($absoluteOriginalPath)) {
            return null;
        }

        $originalBytes = (int) filesize($absoluteOriginalPath);
        if ($originalBytes <= 0) {
            return null;
        }

        $size = @getimagesize($absoluteOriginalPath);
        if ($size === false || $size[0] < 2 || $size[1] < 2) {
            return null;
        }

        $bin = @file_get_contents($absoluteOriginalPath);
        if ($bin === false || $bin === '') {
            return null;
        }

        $src = @imagecreatefromstring($bin);
        if ($src === false) {
            return null;
        }

        $usedCrop = false;
        $working = $src;

        if ($cropBasisPoints !== null && LimoPlateCropExtractor::validateBasisPoints($cropBasisPoints)) {
            $rect = LimoPlateCropExtractor::basisPointsToPixels($size[0], $size[1], $cropBasisPoints);
            if ($rect !== null) {
                $expanded = $this->expandRectWithMargin($rect, $size[0], $size[1]);
                $cropped = @imagecrop($working, [
                    'x' => $expanded['x'],
                    'y' => $expanded['y'],
                    'width' => $expanded['w'],
                    'height' => $expanded['h'],
                ]);
                if ($cropped !== false) {
                    if ($working !== $src) {
                        imagedestroy($working);
                    }
                    imagedestroy($src);
                    $working = $cropped;
                    $usedCrop = true;
                }
            }
        }

        $resized = $this->resizeToMaxLongEdge($working, self::MAX_LONG_EDGE_PX);
        if ($resized !== $working) {
            imagedestroy($working);
            $working = $resized;
        }

        $flattened = $this->flattenForJpeg($working);
        if ($flattened !== $working) {
            imagedestroy($working);
            $working = $flattened;
        }

        $dir = storage_path('framework/cache');
        if (! is_dir($dir) && ! @mkdir($dir, 0750, true) && ! is_dir($dir)) {
            imagedestroy($working);

            return null;
        }

        $tmp = $dir.'/limo_plate_archive_'.Str::random(24).'.jpg';
        if (! @imagejpeg($working, $tmp, self::JPEG_QUALITY)) {
            imagedestroy($working);

            return null;
        }
        imagedestroy($working);

        $archiveBytes = (int) filesize($tmp);
        if ($archiveBytes <= 0) {
            @unlink($tmp);

            return null;
        }

        return new ArchiveDerivativeResult(
            absolutePath: $tmp,
            originalBytes: $originalBytes,
            archiveBytes: $archiveBytes,
            options: [
                'max_long_edge_px' => self::MAX_LONG_EDGE_PX,
                'jpeg_quality' => self::JPEG_QUALITY,
                'crop_margin_fraction' => self::CROP_MARGIN_FRACTION,
                'used_user_crop' => $usedCrop,
                'grayscale' => false,
            ],
        );
    }

    /**
     * @param  array{x:int,y:int,w:int,h:int}  $rect
     * @return array{x:int,y:int,w:int,h:int}
     */
    private function expandRectWithMargin(array $rect, int $imageWidth, int $imageHeight): array
    {
        $marginX = (int) max(1, round($rect['w'] * self::CROP_MARGIN_FRACTION));
        $marginY = (int) max(1, round($rect['h'] * self::CROP_MARGIN_FRACTION));
        $x = max(0, $rect['x'] - $marginX);
        $y = max(0, $rect['y'] - $marginY);
        $w = min($imageWidth - $x, $rect['w'] + (2 * $marginX));
        $h = min($imageHeight - $y, $rect['h'] + (2 * $marginY));

        return ['x' => $x, 'y' => $y, 'w' => max(2, $w), 'h' => max(2, $h)];
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function resizeToMaxLongEdge($image, int $maxLongEdge)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $long = max($w, $h);
        if ($long <= $maxLongEdge) {
            return $image;
        }

        $scale = $maxLongEdge / $long;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            return $image;
        }
        imagecopyresampled($dst, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function flattenForJpeg($image)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $dst = imagecreatetruecolor($w, $h);
        if ($dst === false) {
            return $image;
        }
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $w, $h, $white);
        imagecopy($dst, $image, 0, 0, 0, 0, $w, $h);

        return $dst;
    }
}
