<?php

namespace Tests\Feature;

use App\Models\User;
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
                'password' => 'password',
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
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect(route('panel.user', absolute: false));

        $this->assertNotNull($user->fresh());
    }
}
