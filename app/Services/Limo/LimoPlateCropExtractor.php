<?php

namespace App\Services\Limo;

use Illuminate\Support\Str;

/**
 * Extracts a plate region from an upload using basis-point coordinates (0–10000) relative to image size.
 */
final class LimoPlateCropExtractor
{
    public const BASIS_MAX = 10000;

    public const MIN_SIDE_BP = 100;

    /**
     * @param  array{left:int,top:int,width:int,height:int}  $bp
     * @return array{x:int,y:int,w:int,h:int}|null
     */
    public static function basisPointsToPixels(int $imageWidth, int $imageHeight, array $bp): ?array
    {
        if ($imageWidth < 2 || $imageHeight < 2) {
            return null;
        }
        $x = (int) floor($bp['left'] * $imageWidth / self::BASIS_MAX);
        $y = (int) floor($bp['top'] * $imageHeight / self::BASIS_MAX);
        $w = (int) floor($bp['width'] * $imageWidth / self::BASIS_MAX);
        $h = (int) floor($bp['height'] * $imageHeight / self::BASIS_MAX);
        $w = max(2, $w);
        $h = max(2, $h);
        if ($x + $w > $imageWidth) {
            $w = $imageWidth - $x;
        }
        if ($y + $h > $imageHeight) {
            $h = $imageHeight - $y;
        }
        if ($w < 2 || $h < 2 || $x < 0 || $y < 0) {
            return null;
        }

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }

    /**
     * @param  array{left:int,top:int,width:int,height:int}  $bp
     */
    public static function validateBasisPoints(array $bp): bool
    {
        foreach (['left', 'top', 'width', 'height'] as $k) {
            if (! isset($bp[$k]) || ! is_int($bp[$k])) {
                return false;
            }
        }
        if ($bp['left'] < 0 || $bp['top'] < 0 || $bp['left'] > self::BASIS_MAX || $bp['top'] > self::BASIS_MAX) {
            return false;
        }
        if ($bp['width'] < self::MIN_SIDE_BP || $bp['height'] < self::MIN_SIDE_BP) {
            return false;
        }
        if ($bp['left'] + $bp['width'] > self::BASIS_MAX || $bp['top'] + $bp['height'] > self::BASIS_MAX) {
            return false;
        }

        return true;
    }

    /**
     * Writes a PNG crop to storage/framework/cache and returns its absolute path, or null on failure.
     *
     * @param  array{left:int,top:int,width:int,height:int}  $bp
     */
    public static function extractToTempPng(string $absoluteOriginalPath, array $bp): ?string
    {
        if (! is_file($absoluteOriginalPath) || ! is_readable($absoluteOriginalPath)) {
            return null;
        }
        $size = @getimagesize($absoluteOriginalPath);
        if ($size === false || $size[0] < 2 || $size[1] < 2) {
            return null;
        }
        $rect = self::basisPointsToPixels($size[0], $size[1], $bp);
        if ($rect === null) {
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
        $cropped = @imagecrop($src, [
            'x' => $rect['x'],
            'y' => $rect['y'],
            'width' => $rect['w'],
            'height' => $rect['h'],
        ]);
        imagedestroy($src);
        if ($cropped === false) {
            return null;
        }
        $dir = storage_path('framework/cache');
        if (! is_dir($dir) && ! @mkdir($dir, 0750, true) && ! is_dir($dir)) {
            imagedestroy($cropped);

            return null;
        }
        $tmp = $dir.'/limo_plate_crop_'.Str::random(24).'.png';
        if (! @imagepng($cropped, $tmp)) {
            imagedestroy($cropped);

            return null;
        }
        imagedestroy($cropped);

        return $tmp;
    }
}
