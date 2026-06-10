<?php

namespace Tests\Feature\Guest;

use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\Reservation\ReservationVehicleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTypeDescriptionInReserveFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_reserve_vehicle_type_dropdown_shows_localized_description_cg(): void
    {
        $vt = VehicleType::query()->create(['price' => 40]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Srednji autobus',
            'description' => '9–23 sjedišta',
        ]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'en',
            'name' => 'Medium bus',
            'description' => '9–23 seats',
        ]);

        // Set locale to cg via existing route (session-based).
        $this->get('/locale/cg')->assertRedirect();

        $this->get('/guest/reserve')
            ->assertOk()
            ->assertSee('Srednji autobus (9–23 sjedišta) - 40.00 EUR', false);
    }

    public function test_guest_reserve_vehicle_type_dropdown_shows_localized_description_en(): void
    {
        $vt = VehicleType::query()->create(['price' => 40]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'cg',
            'name' => 'Srednji autobus',
            'description' => '9–23 sjedišta',
        ]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $vt->id,
            'locale' => 'en',
            'name' => 'Medium bus',
            'description' => '9–23 seats',
        ]);

        $this->get('/locale/en')->assertRedirect();

        $this->get('/guest/reserve')
            ->assertOk()
            ->assertSee('Medium bus (9–23 seats) - 40.00 EUR', false);
    }

    public function test_guest_reserve_excludes_limo_passenger_category_from_dropdown(): void
    {
        ReservationVehicleEligibilityService::clearCache();

        $limo = VehicleType::query()->create(['price' => 15]);
        VehicleTypeTranslation::query()->create([
            'vehicle_type_id' => $limo->id,
            'locale' => 'cg',
            'name' => 'Putničko vozilo',
            'description' => '4+1 do 7+1 sjedišta',
        ]);

        $this->get('/guest/reserve')
            ->assertOk()
            ->assertDontSee('Putničko vozilo (4+1 do 7+1 sjedišta)', false);
    }
}

