<?php

namespace Tests\Unit\Limo;

use App\Services\Limo\LimoPlateArchiveDerivativeBuilder;
use App\Services\Limo\LimoPlateCropExtractor;
use Tests\TestCase;

class LimoPlateArchiveDerivativeBuilderTest extends TestCase
{
    public function test_builds_smaller_jpeg_than_large_original(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $dir = storage_path('framework/cache');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $original = $this->writeNoisyPlateJpeg($dir.'/limo_plate_deriv_test_orig_'.uniqid('', true).'.jpg', 2800, 2100);

        $originalBytes = (int) filesize($original);
        $this->assertGreaterThan(100000, $originalBytes);

        $builder = new LimoPlateArchiveDerivativeBuilder;
        $result = $builder->buildForArchive($original, null);

        $this->assertNotNull($result);
        $this->assertFileExists($result->absolutePath);
        $this->assertLessThan($originalBytes, $result->archiveBytes);
        $this->assertSame($originalBytes, $result->originalBytes);
        $this->assertStringEndsWith('.jpg', $result->absolutePath);
        $this->assertFalse($result->options['used_user_crop']);
        $this->assertSame(1600, $result->options['max_long_edge_px']);

        @unlink($result->absolutePath);
        @unlink($original);
    }

    public function test_uses_user_crop_with_margin_when_basis_points_present(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $dir = storage_path('framework/cache');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $original = $dir.'/limo_plate_deriv_crop_'.uniqid('', true).'.jpg';
        $img = imagecreatetruecolor(800, 600);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, 799, 599, $white);
        imagejpeg($img, $original, 95);
        imagedestroy($img);

        $bp = [
            'left' => 2000,
            'top' => 2000,
            'width' => 5000,
            'height' => 4000,
        ];

        $builder = new LimoPlateArchiveDerivativeBuilder;
        $withCrop = $builder->buildForArchive($original, $bp);
        $full = $builder->buildForArchive($original, null);

        $this->assertNotNull($withCrop);
        $this->assertNotNull($full);
        $this->assertTrue($withCrop->options['used_user_crop']);
        $this->assertLessThan($full->archiveBytes, $withCrop->archiveBytes);

        @unlink($withCrop->absolutePath);
        @unlink($full->absolutePath);
        @unlink($original);
    }

    public function test_basis_points_validation_matches_crop_extractor(): void
    {
        $valid = [
            'left' => 1000,
            'top' => 1000,
            'width' => 3000,
            'height' => 3000,
        ];
        $this->assertTrue(LimoPlateCropExtractor::validateBasisPoints($valid));
        $this->assertNotNull(LimoPlateCropExtractor::basisPointsToPixels(800, 600, $valid));
    }

    private function writeNoisyPlateJpeg(string $absolutePath, int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y += 6) {
            for ($x = 0; $x < $width; $x += 6) {
                $c = imagecolorallocate($img, ($x + $y) % 256, ($x * 7) % 256, ($y * 11) % 256);
                imagefilledrectangle($img, $x, $y, min($x + 5, $width - 1), min($y + 5, $height - 1), $c);
            }
        }
        imagejpeg($img, $absolutePath, 95);
        imagedestroy($img);

        return $absolutePath;
    }
}
