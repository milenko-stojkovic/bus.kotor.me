<?php

namespace Tests\Feature\Landing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class UserGuidePdfLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $docsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docsDir = public_path('docs');
        File::ensureDirectoryExists($this->docsDir);
    }

    protected function tearDown(): void
    {
        foreach (['cgbuskotor.pdf', 'engbuskotor.pdf'] as $name) {
            $path = $this->docsDir.DIRECTORY_SEPARATOR.$name;
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_landing_shows_cg_pdf_link_when_file_exists(): void
    {
        file_put_contents($this->docsDir.DIRECTORY_SEPARATOR.'cgbuskotor.pdf', '%PDF-1.4');

        $this->withSession(['locale' => 'cg'])
            ->get(route('landing', [], false))
            ->assertOk()
            ->assertSee('docs/cgbuskotor.pdf', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('download', false);
    }

    public function test_landing_hides_pdf_link_when_file_missing(): void
    {
        $this->withSession(['locale' => 'cg'])
            ->get(route('landing', [], false))
            ->assertOk()
            ->assertDontSee('docs/cgbuskotor.pdf', false);
    }

    public function test_agency_panel_shows_en_pdf_link_when_file_exists(): void
    {
        file_put_contents($this->docsDir.DIRECTORY_SEPARATOR.'engbuskotor.pdf', '%PDF-1.4');

        $user = User::factory()->create();
        app()->setLocale('en');

        $this->actingAs($user)
            ->get(route('panel.reservations', [], false))
            ->assertOk()
            ->assertSee('docs/engbuskotor.pdf', false);
    }
}
