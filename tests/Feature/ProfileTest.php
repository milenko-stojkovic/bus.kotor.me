<?php

namespace Tests\Feature;

use App\Models\FreeReservationRequest;
use App\Models\ListOfTimeSlot;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/panel/user');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'lang' => 'en',
                'country' => 'ME',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('panel.user', absolute: false));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'lang' => $user->lang ?? 'en',
                'country' => $user->country ?? 'ME',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('panel.user', absolute: false));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_password_can_be_updated_via_profile_form(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'lang' => $user->lang ?? 'en',
                'country' => $user->country ?? 'ME',
                'current_password' => 'password',
                'password' => 'new-secure-password-99',
                'password_confirmation' => 'new-secure-password-99',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('panel.user', absolute: false));

        $this->assertTrue(Hash::check('new-secure-password-99', $user->refresh()->password));
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'delete_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_user_can_delete_account_from_panel_user_page(): void
    {
        $user = User::factory()->create([
            'password' => 'Kotor321',
        ]);

        $this->actingAs($user)
            ->get(route('panel.user', [], false))
            ->assertOk()
            ->assertSee('name="password"', false);

        $response = $this->from(route('panel.user', [], false))
            ->delete(route('profile.destroy', [], false), [
                'delete_password' => 'Kotor321',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/panel/user')
            ->delete('/profile', [
                'delete_password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'delete_password')
            ->assertRedirect(route('panel.user', absolute: false));

        $this->assertNotNull($user->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_vehicles_can_delete_account(): void
    {
        $user = User::factory()->create([
            'password' => 'Kotor321',
            'email_verified_at' => now(),
        ]);

        $vehicleType = VehicleType::query()->create(['price' => 10]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'KO111AA',
            'vehicle_type_id' => $vehicleType->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('panel.user', [], false))
            ->delete(route('profile.destroy', [], false), [
                'delete_password' => 'Kotor321',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_user_with_free_reservation_request_can_delete_account(): void
    {
        $user = User::factory()->create([
            'password' => 'Kotor321',
            'email_verified_at' => now(),
        ]);

        $dropOff = ListOfTimeSlot::query()->create(['time_slot' => '08:00 - 08:20']);
        $pickUp = ListOfTimeSlot::query()->create(['time_slot' => '09:00 - 09:20']);

        $freeRequest = FreeReservationRequest::query()->create([
            'user_id' => $user->id,
            'locale' => 'cg',
            'institution_name' => 'Test school',
            'institution_email' => 'school@example.com',
            'institution_phone' => '+38267123456',
            'reservation_date' => now()->addWeek()->toDateString(),
            'drop_off_time_slot_id' => $dropOff->id,
            'pick_up_time_slot_id' => $pickUp->id,
            'country' => 'ME',
            'status' => FreeReservationRequest::STATUS_SUBMITTED,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('panel.user', [], false))
            ->delete(route('profile.destroy', [], false), [
                'delete_password' => 'Kotor321',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertNull(FreeReservationRequest::query()->find($freeRequest->id)?->user_id);
    }
}
