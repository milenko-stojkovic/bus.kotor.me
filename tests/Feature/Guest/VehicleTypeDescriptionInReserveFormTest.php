<?php

namespace Tests\Feature\Guest;

use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTypeDescriptionInReserveFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_reserve_vehicle_type_dropdown_shows_localized_description_cg(): void
    {
        $vt = VehicleType::query()->create(['price' => 12]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Putničko vozilo',
            'description' => 'Automobil (4+1 do 7+1 sjedišta)',
        ]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'en',
            'name' => 'Personal vehicle',
            'description' => 'Passenger car (4+1 to 7+1 seats)',
        ]);

        // Set locale to cg via existing route (session-based).
        $this->get('/locale/cg')->assertRedirect();

        $this->get('/guest/reserve')
            ->assertOk()
            ->assertSee('Putničko vozilo (Automobil (4+1 do 7+1 sjedišta)) - 12.00 EUR', false);
    }

    public function test_guest_reserve_vehicle_type_dropdown_shows_localized_description_en(): void
    {
        $vt = VehicleType::query()->create(['price' => 12]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Putničko vozilo',
            'description' => 'Automobil (4+1 do 7+1 sjedišta)',
        ]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'en',
            'name' => 'Personal vehicle',
            'description' => 'Passenger car (4+1 to 7+1 seats)',
        ]);

        $this->get('/locale/en')->assertRedirect();

        $this->get('/guest/reserve')
            ->assertOk()
            ->assertSee('Personal vehicle (Passenger car (4+1 to 7+1 seats)) - 12.00 EUR', false);
    }
}

