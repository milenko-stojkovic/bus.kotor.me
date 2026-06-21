<?php

namespace Tests\Feature\Vehicles;

use App\Mail\VehicleCategoryChangeRequestMail;
use App\Models\Admin;
use App\Models\AdminAlert;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategoryChangeRequest;
use App\Models\VehicleCategoryChangeRequestAttachment;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VehicleCategoryChangeMultiAttachmentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{a: VehicleType, b: VehicleType, user: User, old: Vehicle} */
    private function seedFixtures(): array
    {
        $a = VehicleType::query()->create(['price' => 10]);
        $b = VehicleType::query()->create(['price' => 20]);
        foreach ([$a, $b] as $t) {
            VehicleTypeTranslation::query()->create(['vehicle_type_id' => $t->id, 'locale' => 'en', 'name' => 'T'.$t->id, 'description' => null]);
            VehicleTypeTranslation::query()->create(['vehicle_type_id' => $t->id, 'locale' => 'cg', 'name' => 'T'.$t->id, 'description' => null]);
        }

        $user = User::factory()->create(['lang' => 'cg', 'email' => 'multi@example.com', 'name' => 'Multi Agency']);
        $old = Vehicle::query()->create([
            'user_id' => $user->id,
            'license_plate' => 'KO222',
            'vehicle_type_id' => $a->id,
            'status' => Vehicle::STATUS_REMOVED,
        ]);

        return compact('a', 'b', 'user', 'old');
    }

    private function seedAdmin(): Admin
    {
        return Admin::query()->create([
            'username' => 'multiadmin',
            'email' => 'multi-admin@example.com',
            'password' => bcrypt('secret-password-multi'),
            'control_access' => false,
            'admin_access' => true,
        ]);
    }

    /** @param array<int, UploadedFile> $files */
    private function submitRequest(array $fixtures, array $files): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('panel.vehicles.category_change_requests.store', [], false), [
            'old_vehicle_id' => $fixtures['old']->id,
            'license_plate' => 'KO222',
            'old_vehicle_type_id' => $fixtures['a']->id,
            'requested_vehicle_type_id' => $fixtures['b']->id,
            'documents' => $files,
        ]);
    }

    public function test_cannot_submit_without_documents(): void
    {
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $this->post(route('panel.vehicles.category_change_requests.store', [], false), [
            'old_vehicle_id' => $fixtures['old']->id,
            'license_plate' => 'KO222',
            'old_vehicle_type_id' => $fixtures['a']->id,
            'requested_vehicle_type_id' => $fixtures['b']->id,
        ])->assertSessionHasErrors('documents');

        $this->assertSame(0, VehicleCategoryChangeRequest::query()->count());
    }

    public function test_can_submit_with_one_document(): void
    {
        Storage::fake('local');
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $file = UploadedFile::fake()->create('front.pdf', 100, 'application/pdf');
        $this->submitRequest($fixtures, [$file])->assertRedirect(route('panel.vehicles', [], false));

        $req = VehicleCategoryChangeRequest::query()->firstOrFail();
        $this->assertSame(1, $req->attachments()->count());
        Storage::disk('local')->assertExists($req->attachments()->first()->path);
    }

    public function test_can_submit_with_two_documents(): void
    {
        Storage::fake('local');
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $files = [
            UploadedFile::fake()->create('front.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('back.jpg'),
        ];

        $this->submitRequest($fixtures, $files)->assertRedirect(route('panel.vehicles', [], false));

        $req = VehicleCategoryChangeRequest::query()->firstOrFail();
        $this->assertSame(2, $req->attachments()->count());
        foreach ($req->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
        }
    }

    public function test_cannot_submit_more_than_five_documents(): void
    {
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $files = [];
        for ($i = 1; $i <= 6; $i++) {
            $files[] = UploadedFile::fake()->create("doc{$i}.pdf", 10, 'application/pdf');
        }

        $this->submitRequest($fixtures, $files)->assertSessionHasErrors('documents');
        $this->assertSame(0, VehicleCategoryChangeRequest::query()->count());
    }

    public function test_invalid_file_type_rejected(): void
    {
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $this->submitRequest($fixtures, [
            UploadedFile::fake()->create('bad.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('documents.0');

        $this->assertSame(0, VehicleCategoryChangeRequest::query()->count());
    }

    public function test_admin_detail_page_shows_all_attachment_preview_links(): void
    {
        Storage::fake('local');
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $this->submitRequest($fixtures, [
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->image('b.jpg'),
        ]);

        $req = VehicleCategoryChangeRequest::query()->firstOrFail();
        $admin = $this->seedAdmin();

        $html = $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.agencies.vehicle_category_change_requests.show', [
                'user' => $fixtures['user']->id,
                'request' => $req->id,
            ], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Dokument 1', $html);
        $this->assertStringContainsString('Dokument 2', $html);
        $this->assertStringContainsString('a.pdf', $html);
        $this->assertStringContainsString('b.jpg', $html);
    }

    public function test_attachment_preview_returns_200_for_existing_file(): void
    {
        Storage::fake('local');
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $this->submitRequest($fixtures, [
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ]);

        $req = VehicleCategoryChangeRequest::query()->firstOrFail();
        $attachment = $req->attachments()->firstOrFail();
        $admin = $this->seedAdmin();

        $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.agencies.vehicle_category_change_requests.attachments.preview', [
                'user' => $fixtures['user']->id,
                'request' => $req->id,
                'attachment' => $attachment->id,
            ], false))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_missing_attachment_returns_404(): void
    {
        Storage::fake('local');
        $fixtures = $this->seedFixtures();

        $req = VehicleCategoryChangeRequest::query()->create([
            'user_id' => $fixtures['user']->id,
            'old_vehicle_id' => $fixtures['old']->id,
            'license_plate' => 'KO222',
            'old_vehicle_type_id' => $fixtures['a']->id,
            'requested_vehicle_type_id' => $fixtures['b']->id,
            'status' => VehicleCategoryChangeRequest::STATUS_PENDING,
            'document_original_name' => 'x.pdf',
            'document_path' => 'vehicle-category-change-requests/9/attachments/1/file',
            'document_mime_type' => 'application/pdf',
            'document_size_bytes' => 10,
            'locale' => 'cg',
        ]);

        $attachment = VehicleCategoryChangeRequestAttachment::query()->create([
            'vehicle_category_change_request_id' => $req->id,
            'disk' => 'local',
            'path' => 'vehicle-category-change-requests/9/attachments/1/file',
            'original_name' => 'x.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
        ]);

        $admin = $this->seedAdmin();

        $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.agencies.vehicle_category_change_requests.attachments.preview', [
                'user' => $fixtures['user']->id,
                'request' => $req->id,
                'attachment' => $attachment->id,
            ], false))
            ->assertNotFound();
    }

    public function test_legacy_document_path_still_previews_via_legacy_route(): void
    {
        Storage::fake('local');
        $fixtures = $this->seedFixtures();

        $req = VehicleCategoryChangeRequest::query()->create([
            'user_id' => $fixtures['user']->id,
            'old_vehicle_id' => $fixtures['old']->id,
            'license_plate' => 'KO222',
            'old_vehicle_type_id' => $fixtures['a']->id,
            'requested_vehicle_type_id' => $fixtures['b']->id,
            'status' => VehicleCategoryChangeRequest::STATUS_PENDING,
            'document_original_name' => 'legacy.pdf',
            'document_path' => 'vehicle-category-change-requests/legacy/document',
            'document_mime_type' => 'application/pdf',
            'document_size_bytes' => 100,
            'locale' => 'cg',
        ]);
        Storage::disk('local')->put($req->document_path, 'PDF');

        $admin = $this->seedAdmin();

        $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.agencies.vehicle_category_change_requests.document', [
                'user' => $fixtures['user']->id,
                'request' => $req->id,
            ], false))
            ->assertOk();

        $html = $this->actingAs($admin, 'panel_admin')
            ->get(route('panel_admin.agencies.vehicle_category_change_requests.show', [
                'user' => $fixtures['user']->id,
                'request' => $req->id,
            ], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('legacy.pdf', $html);
    }

    public function test_approval_works_with_multiple_attachments(): void
    {
        Storage::fake('local');
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $this->submitRequest($fixtures, [
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->image('b.jpg'),
        ]);

        $req = VehicleCategoryChangeRequest::query()->firstOrFail();
        AdminAlert::query()->create([
            'type' => 'vehicle_category_change_request',
            'status' => AdminAlert::STATUS_UNREAD,
            'title' => 't',
            'message' => 'm',
            'payload_json' => [
                'vehicle_category_change_request_id' => (int) $req->id,
                'user_id' => (int) $fixtures['user']->id,
                'license_plate' => 'KO222',
            ],
        ]);

        $admin = $this->seedAdmin();
        $this->actingAs($admin, 'panel_admin');

        $this->post(route('panel_admin.agencies.vehicle_category_change_requests.approve', [
            'user' => $fixtures['user']->id,
            'request' => $req->id,
        ], false))->assertRedirect(route('panel_admin.agencies.show', $fixtures['user'], false));

        $req->refresh();
        $fixtures['old']->refresh();
        $this->assertSame(VehicleCategoryChangeRequest::STATUS_APPROVED, (string) $req->status);
        $this->assertSame(Vehicle::STATUS_ACTIVE, (string) $fixtures['old']->status);
        $this->assertSame(2, $req->attachments()->count());
    }

    public function test_mail_mentions_attachment_count_and_review_link(): void
    {
        Storage::fake('local');
        Mail::fake();
        $fixtures = $this->seedFixtures();
        $this->actingAs($fixtures['user']);

        $this->submitRequest($fixtures, [
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->image('b.jpg'),
        ]);

        Mail::assertSent(VehicleCategoryChangeRequestMail::class, function (VehicleCategoryChangeRequestMail $mail): bool {
            return $mail->attachmentCount === 2 && $mail->adminReviewUrl !== '';
        });
    }
}
