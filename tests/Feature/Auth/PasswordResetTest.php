<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\NoreplyResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_forgot_password_page_shows_english_when_locale_is_en(): void
    {
        $this->get('/locale/en')->assertRedirect();

        $html = $this->get('/forgot-password')->assertOk()->getContent();

        $this->assertStringContainsString('Forgot your password? No problem.', $html);
        $this->assertStringContainsString('Email Password Reset Link', $html);
        $this->assertStringNotContainsString('Zaboravili ste lozinku', $html);
        $this->assertStringNotContainsString('Pošalji link za reset lozinke', $html);
    }

    public function test_forgot_password_page_shows_cg_when_locale_is_cg(): void
    {
        $this->get('/locale/cg')->assertRedirect();

        $html = $this->get('/forgot-password')->assertOk()->getContent();

        $this->assertStringContainsString('Zaboravili ste lozinku? Nema problema.', $html);
        $this->assertStringContainsString('Pošalji link za reset lozinke', $html);
        $this->assertStringNotContainsString('Forgot your password? No problem.', $html);
        $this->assertStringNotContainsString('Email Password Reset Link', $html);
    }

    public function test_forgot_password_page_prefers_english_fallback_over_other_locale_row_when_en_locale_missing_key(): void
    {
        $now = now();
        \Illuminate\Support\Facades\DB::table('ui_translations')->insert([
            [
                'group' => 'auth',
                'key' => 'forgot_password_prompt',
                'locale' => 'cg',
                'text' => 'Zaboravili ste lozinku? Nema problema. Unesite svoju email adresu i poslaćemo vam link za reset lozinke pomoću kojeg možete odabrati novu.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'auth',
                'key' => 'forgot_password_send_link',
                'locale' => 'cg',
                'text' => 'Pošalji link za reset lozinke',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->get('/locale/en')->assertRedirect();

        $html = $this->get('/forgot-password')->assertOk()->getContent();

        $this->assertStringContainsString('Forgot your password? No problem.', $html);
        $this->assertStringContainsString('Email Password Reset Link', $html);
        $this->assertStringNotContainsString('Zaboravili ste lozinku? Nema problema.', $html);
        $this->assertStringNotContainsString('Pošalji link za reset lozinke', $html);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, NoreplyResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, NoreplyResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, NoreplyResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
