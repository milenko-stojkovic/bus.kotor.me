<?php

namespace Tests\Unit;

use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class VehicleTypeLabelFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_label_includes_description_in_parentheses_and_price(): void
    {
        $vt = VehicleType::query()->create(['price' => 12]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Putničko vozilo',
            'description' => 'Automobil (4+1 do 7+1 sjedišta)',
        ]);

        $vt->load('translations');
        $this->assertSame(
            'Putničko vozilo (Automobil (4+1 do 7+1 sjedišta)) - 12.00 EUR',
            $vt->formatLabel('cg', 'EUR')
        );
    }

    public function test_format_label_falls_back_to_name_without_parentheses_when_description_missing(): void
    {
        $vt = VehicleType::query()->create(['price' => 12]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'en',
            'name' => 'Personal vehicle',
            'description' => null,
        ]);

        $vt->load('translations');
        $this->assertSame(
            'Personal vehicle - 12.00 EUR',
            $vt->formatLabel('en', 'EUR')
        );
    }

    public function test_format_label_falls_back_to_hash_id_and_logs_warning_when_translation_missing(): void
    {
        $vt = VehicleType::query()->create(['price' => 12]);
        $vt->load('translations');

        Log::shouldReceive('warning')
            ->once()
            ->with('vehicle_type_translation_missing', Mockery::on(function (array $context) use ($vt): bool {
                return ($context['vehicle_type_id'] ?? null) === $vt->id
                    && ($context['locale'] ?? null) === 'cg'
                    && ($context['has_name_translation'] ?? null) === false
                    && ($context['has_description_translation'] ?? null) === false;
            }));

        $label = $vt->formatLabel('cg', 'EUR');
        $this->assertSame('#'.$vt->id.' - 12.00 EUR', $label);
    }
}

