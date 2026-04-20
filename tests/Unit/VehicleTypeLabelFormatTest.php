<?php

namespace Tests\Unit;

use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

