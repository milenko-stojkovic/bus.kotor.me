<?php

namespace Tests\Feature\Limo;

use App\Jobs\ProcessLimoAfterPaymentJob;
use App\Models\Admin;
use App\Models\AgencyAdvanceTransaction;
use App\Models\LimoPickupEvent;
use App\Models\LimoPickupPhoto;
use App\Models\LimoPlateUpload;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Services\Limo\LimoOcrRunner;
use App\Services\Limo\LimoPlateImagePreprocessor;

class LimoPlateFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-04 12:00:00', 'Europe/Podgorica'));
        config(['limo.ocr.enabled' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_upload_plate_photo_returns_upload_token(): void
    {
        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_up');

        $file = UploadedFile::fake()->image('plate.jpg', 640, 480);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => $file,
                'device_info' => '{"t":1}',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['upload_token', 'suggested_plate']);

        $data = $response->json();
        $this->assertNotEmpty($data['upload_token']);
        $this->assertNull($data['suggested_plate']);

        $this->assertSame(1, LimoPlateUpload::query()->count());
    }

    public function test_ocr_disabled_upload_still_ok_and_suggestion_null(): void
    {
        config(['limo.ocr.enabled' => false]);
        Storage::fake('local');

        $admin = $this->makeLimoAdmin('plate_ocr_off');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', null);
    }

    public function test_ocr_noisy_text_suggests_normalized_plate(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return "xx\\nPG 123-AB\\nnoise";
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_ok');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', 'PG123AB');
    }

    public function test_ocr_failure_does_not_break_upload_flow(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                throw new \RuntimeException('tesseract timeout');
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_fail');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', null);
    }

    public function test_ocr_spaced_and_dash_format_suggests_ko123ab(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return 'KO - 123 AB';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_ko');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', 'KO123AB');
    }

    public function test_ocr_no_plate_candidate_returns_null_but_upload_ok(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return 'ABCDEFGHJK';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_nocand');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', null);

        $this->assertNotNull(LimoPlateUpload::query()->latest('id')->value('upload_token'));
    }

    public function test_plate_ocr_response_includes_debug_when_app_debug_true(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.debug_extended_attempts' => false,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return 'PG 999-ZZ';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_dbg_on');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('debug', $json);
        $this->assertTrue($json['debug']['ocr_enabled']);
        $this->assertTrue($json['debug']['ocr_available']);
        $this->assertSame('ok', $json['debug']['reason']);
        $this->assertNotSame('', $json['debug']['normalized_preview']);
        $this->assertSame('PG999ZZ', $json['suggested_plate']);
        $this->assertNotEmpty($json['debug']['variants_tried']);
        $this->assertIsArray($json['debug']['variant_attempts']);
        $this->assertNotEmpty($json['debug']['variant_attempts']);
        $this->assertContains('original@psm=7@wl=1', $json['debug']['variants_tried']);
    }

    public function test_ocr_raw_nk_at_bp505_suggests_nkbp505(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return 'NK @ BP505';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_nk');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('suggested_plate', 'NKBP505');
    }

    public function test_ocr_eventually_prefers_pg123ab_over_noise(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            private int $n = 0;

            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                $this->n++;
                if ($this->n < 8) {
                    return '@@@###';
                }

                return "xx\nPG123AB\nnoise";
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_pick');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('suggested_plate', 'PG123AB');
    }

    public function test_ocr_all_empty_outputs_yields_null_suggestion(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return '';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_empty');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('suggested_plate', null);
    }

    public function test_debug_variant_attempts_include_raw_normalized_candidate_error_keys(): void
    {
        config(['limo.ocr.enabled' => true, 'app.debug' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return '';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_dbg_fields');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('debug', $json);
        $this->assertNotEmpty($json['debug']['variant_attempts']);
        $row = $json['debug']['variant_attempts'][0];
        $this->assertArrayHasKey('variant', $row);
        $this->assertArrayHasKey('psm', $row);
        $this->assertArrayHasKey('raw_preview', $row);
        $this->assertSame('', $row['raw_preview']);
        $this->assertArrayHasKey('normalized_preview', $row);
        $this->assertSame('', $row['normalized_preview']);
        $this->assertArrayHasKey('candidate', $row);
        $this->assertNull($row['candidate']);
        $this->assertArrayHasKey('whitelist_enabled', $row);
        $this->assertTrue($row['whitelist_enabled']);
    }

    public function test_ocr_stops_early_after_high_confidence_strict_plate(): void
    {
        config(['limo.ocr.enabled' => true, 'app.debug' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            private static int $calls = 0;

            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                self::$calls++;
                if (self::$calls > 1) {
                    throw new \RuntimeException('OCR should have stopped after first high-confidence pass');
                }

                return 'PG123AB';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_early_exit');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->assertSame('PG123AB', $json['suggested_plate']);
        $this->assertArrayHasKey('debug', $json);
        $this->assertTrue($json['debug']['early_exit']);
        $this->assertSame(1, count($json['debug']['variant_attempts']));
    }

    public function test_ocr_max_total_seconds_zero_limits_to_single_attempt_slice(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.max_total_seconds' => 0,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return '';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_max0');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->assertNull($json['suggested_plate']);
        $this->assertNotEmpty($json['debug']['variant_attempts'] ?? []);
        $this->assertLessThanOrEqual(1, count($json['debug']['variant_attempts']));
        $this->assertArrayHasKey('attempts_count', $json['debug']);
        $this->assertSame(count($json['debug']['variant_attempts']), $json['debug']['attempts_count']);
    }

    public function test_debug_ocr_disabled_returns_synthetic_variant_attempts_when_app_debug_true(): void
    {
        config(['limo.ocr.enabled' => false, 'app.debug' => true]);
        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_dbg_disabled');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('debug', $json);
        $this->assertFalse($json['debug']['ocr_enabled']);
        $this->assertFalse($json['debug']['ocr_available']);
        $this->assertCount(1, $json['debug']['variant_attempts']);
        $row = $json['debug']['variant_attempts'][0];
        $this->assertSame('none', $row['variant']);
        $this->assertNull($row['psm']);
        $this->assertSame('', $row['raw_preview']);
        $this->assertSame('', $row['normalized_preview']);
        $this->assertNull($row['candidate']);
        $this->assertNotEmpty($row['error']);
        $this->assertSame(0, $json['debug']['variants_count']);
        $this->assertSame(1, $json['debug']['attempts_count']);
        $this->assertFalse($json['debug']['preprocessing_available']);
        $this->assertFalse($json['debug']['preprocessing_failed']);
        $this->assertArrayHasKey('failure_detail', $json['debug']);
    }

    public function test_debug_ocr_unavailable_returns_synthetic_variant_attempts_when_app_debug_true(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.tesseract_binary' => base_path('nonexistent-tesseract-binary-for-test.bin'),
        ]);
        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_dbg_unavail');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('debug', $json);
        $this->assertTrue($json['debug']['ocr_enabled']);
        $this->assertFalse($json['debug']['ocr_available']);
        $this->assertCount(1, $json['debug']['variant_attempts']);
        $row = $json['debug']['variant_attempts'][0];
        $this->assertSame('none', $row['variant']);
        $this->assertNull($row['psm']);
        $this->assertStringContainsString('tesseract_binary_not_found', (string) $row['error']);
        $this->assertFalse($json['debug']['preprocessing_available']);
        $this->assertArrayHasKey('failure_detail', $json['debug']);
    }

    public function test_debug_normal_ocr_attempt_returns_at_least_one_non_synthetic_variant_attempt_row(): void
    {
        config(['limo.ocr.enabled' => true, 'app.debug' => true]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return 'PG 888-AA';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_dbg_real_rows');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 320, 240),
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('debug', $json);
        $this->assertNotEmpty($json['debug']['variant_attempts']);
        $hasNonSynthetic = false;
        foreach ($json['debug']['variant_attempts'] as $r) {
            if (($r['variant'] ?? '') !== 'none') {
                $hasNonSynthetic = true;
                break;
            }
        }
        $this->assertTrue($hasNonSynthetic);
        $this->assertGreaterThanOrEqual(1, $json['debug']['variants_count']);
        $this->assertTrue($json['debug']['preprocessing_available']);
        $this->assertFalse($json['debug']['preprocessing_failed']);
    }

    public function test_debug_save_images_writes_copies_and_returns_paths_and_dimensions(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.debug_save_images' => true,
            'limo.ocr.debug_image_ttl_minutes' => 60,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return '';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_dbg_save');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 320, 240),
            ])
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('debug', $json);
        $this->assertNotEmpty($json['debug']['variant_attempts']);
        $row = $json['debug']['variant_attempts'][0];
        $this->assertArrayHasKey('debug_image_path', $row);
        $this->assertIsString($row['debug_image_path']);
        $this->assertStringStartsWith('limo_ocr_debug/', $row['debug_image_path']);
        $this->assertStringContainsString('_wl1', $row['debug_image_path']);
        Storage::disk('local')->assertExists($row['debug_image_path']);
        $this->assertArrayHasKey('image_width', $row);
        $this->assertArrayHasKey('image_height', $row);
        $this->assertGreaterThan(0, $row['image_width']);
        $this->assertGreaterThan(0, $row['image_height']);
        $this->assertTrue($row['input_exists']);
        $this->assertGreaterThan(0, $row['input_size_bytes']);
    }

    public function test_plate_ocr_debug_variant_error_is_not_short_truncated_for_exceptions(): void
    {
        config(['limo.ocr.enabled' => true, 'app.debug' => true]);
        $payload = str_repeat('Z', 400);
        $this->app->bind(LimoOcrRunner::class, fn () => new class($payload) implements LimoOcrRunner {
            public function __construct(private string $payload) {}

            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                throw new \RuntimeException('simulated_tesseract_failure '.$this->payload);
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_long_err');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 180, 140),
            ])
            ->assertOk()
            ->json();

        $err = (string) ($json['debug']['variant_attempts'][0]['error'] ?? '');
        $this->assertStringContainsString('simulated_tesseract_failure', $err);
        $this->assertGreaterThan(200, strlen($err));
    }

    public function test_ocr_extended_whitelist_off_finds_candidate_when_whitelist_returns_empty(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.debug_extended_attempts' => true,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                if (($options['whitelist'] ?? true) === true) {
                    return '';
                }

                return 'ME 12 34 AB';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_wl_off');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 320, 240),
            ])
            ->assertOk()
            ->assertJsonPath('suggested_plate', 'ME1234AB');
    }

    public function test_ocr_extended_psm_eleven_finds_candidate(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.debug_extended_attempts' => true,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                if ((int) ($options['psm'] ?? 0) === 11) {
                    return 'PG 777 ZZ';
                }

                return '';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_psm11');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 280, 200),
            ])
            ->assertOk()
            ->assertJsonPath('suggested_plate', 'PG777ZZ');
    }

    public function test_ocr_upscaled_threshold_variant_finds_candidate(): void
    {
        config(['limo.ocr.enabled' => true, 'app.debug' => true, 'limo.ocr.debug_extended_attempts' => false]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                if (str_contains($absoluteImagePath, 'c_gray_threshold_scale2')) {
                    return 'KO 888 YY';
                }

                return '';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_scale2');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 400, 300),
            ])
            ->assertOk()
            ->assertJsonPath('suggested_plate', 'KO888YY');
    }

    public function test_ocr_production_compact_matrix_attempt_count_matches_variants_times_two(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.debug_extended_attempts' => false,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return '';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_compact');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 360, 280),
            ])
            ->assertOk()
            ->json();

        $variants = (int) $json['debug']['variants_count'];
        $attempts = (int) $json['debug']['attempts_count'];
        $this->assertGreaterThan(0, $variants);
        $this->assertSame($variants * 2, $attempts);
    }

    public function test_preprocess_exception_falls_back_and_upload_still_ok(): void
    {
        config(['limo.ocr.enabled' => true]);

        $this->app->bind(LimoPlateImagePreprocessor::class, fn () => new class extends LimoPlateImagePreprocessor {
            public function buildVariants(string $absoluteOriginalPath): array
            {
                throw new \RuntimeException('preprocess_test_boom');
            }
        });

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return '';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_preproc_fail');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('suggested_plate', null);
    }

    public function test_plate_ocr_response_excludes_debug_when_app_debug_false(): void
    {
        config(['limo.ocr.enabled' => true, 'app.debug' => false]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                return 'PG111AA';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_ocr_dbg_off');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('debug', $json);
        $this->assertSame('PG111AA', $json['suggested_plate']);
    }

    public function test_confirm_registered_plate_with_advance_creates_event_attaches_photo_and_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeLimoAdmin('plate_ok');
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);
        $vt = VehicleType::query()->create(['price' => 12]);
        Vehicle::query()->create([
            'user_id' => $agency->id,
            'license_plate' => 'PGTEST99',
            'vehicle_type_id' => $vt->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 400, 300),
            ])
            ->assertOk()
            ->json();

        $confirm = $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'PG-TEST-99',
                'gps_lat' => 42.2,
                'gps_lng' => 18.7,
                'device_info' => '{}',
            ])
            ->assertOk()
            ->json();

        $this->assertSame('ok', $confirm['status']);
        $this->assertArrayHasKey('merchant_transaction_id', $confirm);

        $event = LimoPickupEvent::query()->where('license_plate_snapshot', 'PGTEST99')->first();
        $this->assertNotNull($event);
        $this->assertSame('plate', $event->source);
        $this->assertSame('pending_fiscal', $event->status);

        $this->assertSame(1, LimoPickupPhoto::query()->where('limo_pickup_event_id', $event->id)->where('type', 'plate')->count());

        $this->assertDatabaseHas('agency_advance_transactions', [
            'agency_user_id' => $agency->id,
            'type' => AgencyAdvanceTransaction::TYPE_USAGE,
            'amount' => '-15.00',
        ]);

        Queue::assertPushed(ProcessLimoAfterPaymentJob::class, fn (ProcessLimoAfterPaymentJob $job) => $job->limoPickupEventId === $event->id);

        $this->assertNotNull(LimoPlateUpload::query()->where('upload_token', $upload['upload_token'])->value('consumed_at'));
    }

    public function test_unregistered_plate_returns_plate_not_registered(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeLimoAdmin('plate_nr');

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'ZZ999ZZ',
            ])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'plate_not_registered',
                'message' => 'Tablica nije pronađena u voznom parku nijedne agencije.',
            ]);

        $this->assertSame(0, LimoPickupEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_insufficient_advance_returns_error_without_event(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeLimoAdmin('plate_low');
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '10.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);
        $vt = VehicleType::query()->create(['price' => 12]);
        Vehicle::query()->create([
            'user_id' => $agency->id,
            'license_plate' => 'LOWBAL01',
            'vehicle_type_id' => $vt->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'LOWBAL01',
            ])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'insufficient_advance',
                'message' => 'Agencija nema dovoljno avansa.',
            ]);

        $this->assertSame(0, LimoPickupEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_upload_token_cannot_be_reused(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->makeLimoAdmin('plate_reuse');
        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);
        $vt = VehicleType::query()->create(['price' => 12]);
        Vehicle::query()->create([
            'user_id' => $agency->id,
            'license_plate' => 'REUSE01',
            'vehicle_type_id' => $vt->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->json();

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'REUSE01',
            ])
            ->assertOk();

        $this->actingAs($admin, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'REUSE01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_upload')
            ->assertJsonPath('message', 'Fotografija više nije važeća. Pokušajte ponovo.');
    }

    public function test_another_evidenter_cannot_consume_foreign_upload_token(): void
    {
        Storage::fake('local');
        Queue::fake();

        $adminA = $this->makeLimoAdmin('plate_a');
        $adminB = $this->makeLimoAdmin('plate_b');

        $agency = User::factory()->create();
        AgencyAdvanceTransaction::query()->create([
            'agency_user_id' => $agency->id,
            'amount' => '100.00',
            'type' => AgencyAdvanceTransaction::TYPE_TOPUP,
            'reference_type' => null,
            'reference_id' => null,
            'merchant_transaction_id' => null,
            'note' => 'topup',
        ]);
        $vt = VehicleType::query()->create(['price' => 12]);
        Vehicle::query()->create([
            'user_id' => $agency->id,
            'license_plate' => 'OTHER01',
            'vehicle_type_id' => $vt->id,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $upload = $this->actingAs($adminA, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('x.jpg', 200, 200),
            ])
            ->assertOk()
            ->json();

        $this->actingAs($adminB, 'panel_admin')
            ->postJson('/limo/pickup/plate/confirm', [
                'upload_token' => $upload['upload_token'],
                'license_plate' => 'OTHER01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_upload')
            ->assertJsonPath('message', 'Fotografija više nije važeća. Pokušajte ponovo.');

        $this->assertSame(0, LimoPickupEvent::query()->count());
    }

    public function test_upload_with_plate_crop_basis_points_stores_crop_and_ocr_prefers_crop_reading(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.debug_extended_attempts' => false,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                $g = @getimagesize($absoluteImagePath);
                $w = is_array($g) ? (int) $g[0] : 9999;

                return $w <= 180 ? 'NK BP 505' : 'SZ SN 257';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_crop_pref');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
                'plate_crop_left' => 4000,
                'plate_crop_top' => 3750,
                'plate_crop_width' => 2000,
                'plate_crop_height' => 2500,
            ])
            ->assertOk()
            ->json();

        $this->assertSame('NKBP505', $json['suggested_plate']);
        $this->assertArrayHasKey('debug', $json);
        $this->assertTrue($json['debug']['ocr_used_user_crop']);
        $this->assertNotNull($json['debug']['ocr_crop_width_px']);
        $this->assertNotNull($json['debug']['ocr_crop_height_px']);
        $this->assertLessThanOrEqual(180, (int) $json['debug']['ocr_crop_width_px']);
        $this->assertStringStartsWith('uc_', (string) $json['debug']['selected_variant']);

        $row = LimoPlateUpload::query()->where('upload_token', $json['upload_token'])->first();
        $this->assertNotNull($row);
        $this->assertSame(4000, (int) $row->plate_crop_left_bp);
        $this->assertSame(3750, (int) $row->plate_crop_top_bp);
        $this->assertSame(2000, (int) $row->plate_crop_width_bp);
        $this->assertSame(2500, (int) $row->plate_crop_height_bp);
    }

    public function test_upload_without_plate_crop_uses_full_image_ocr_even_when_runner_differs_by_width(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.debug_extended_attempts' => false,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                $g = @getimagesize($absoluteImagePath);
                $w = is_array($g) ? (int) $g[0] : 9999;

                return $w <= 180 ? 'NK BP 505' : 'SZ SN 257';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_no_crop');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $json = $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
            ])
            ->assertOk()
            ->json();

        $this->assertSame('SZSN257', $json['suggested_plate']);
        $this->assertArrayHasKey('debug', $json);
        $this->assertFalse($json['debug']['ocr_used_user_crop']);
        $this->assertNull($json['debug']['ocr_crop_width_px']);
        $this->assertNull($json['debug']['ocr_crop_height_px']);

        $row = LimoPlateUpload::query()->where('upload_token', $json['upload_token'])->first();
        $this->assertNotNull($row);
        $this->assertNull($row->plate_crop_left_bp);
        $this->assertNull($row->plate_crop_top_bp);
        $this->assertNull($row->plate_crop_width_bp);
        $this->assertNull($row->plate_crop_height_bp);
    }

    public function test_plate_crop_basis_points_validation_error_returns_422(): void
    {
        config(['limo.ocr.enabled' => true]);
        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_crop_bad');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('plate.jpg', 640, 480),
                'plate_crop_left' => 9500,
                'plate_crop_top' => 0,
                'plate_crop_width' => 1000,
                'plate_crop_height' => 1000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_ocr_extended_psm11_on_original_file_only_does_not_suggest_plate_without_corroboration(): void
    {
        config([
            'limo.ocr.enabled' => true,
            'app.debug' => true,
            'limo.ocr.debug_extended_attempts' => true,
        ]);

        $this->app->bind(LimoOcrRunner::class, fn () => new class implements LimoOcrRunner {
            public function run(string $absoluteImagePath, int $timeoutSeconds, array $options = []): string
            {
                if (! str_contains($absoluteImagePath, 'a_original.png')) {
                    return '';
                }
                if ((int) ($options['psm'] ?? 0) !== 11) {
                    return '';
                }

                return 'PG 777 ZZ';
            }
        });

        Storage::fake('local');
        $admin = $this->makeLimoAdmin('plate_psm11_orig_only');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin, 'panel_admin')
            ->post('/limo/pickup/plate/ocr', [
                'image' => UploadedFile::fake()->image('p.jpg', 280, 200),
            ])
            ->assertOk()
            ->assertJsonPath('suggested_plate', null);
    }

    private function makeLimoAdmin(string $username): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => bcrypt('secret'),
            'control_access' => false,
            'admin_access' => false,
            'limo_access' => true,
        ]);
    }
}
