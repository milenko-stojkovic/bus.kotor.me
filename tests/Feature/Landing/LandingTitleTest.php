<?php

namespace Tests\Feature\Landing;

use Database\Seeders\UiTranslationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LandingTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UiTranslationsSeeder::class);
    }

    public function test_landing_title_is_two_centered_lines_in_cg(): void
    {
        $html = $this->withSession(['locale' => 'cg'])
            ->get(route('landing', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('text-center', $html);
        $this->assertStringContainsString('Plaćanje naknade', $html);
        $this->assertStringContainsString('za ekonomsko iskorišćavanje kulturnih dobara', $html);
    }

    public function test_landing_title_is_two_centered_lines_in_en(): void
    {
        $html = $this->withSession(['locale' => 'en'])
            ->get(route('landing', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('text-center', $html);
        $this->assertStringContainsString('Payment of the fee', $html);
        $this->assertStringContainsString('for the economic use of cultural assets', $html);
    }
}
