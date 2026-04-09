<?php

namespace Tests\Feature\Payment;

use App\Jobs\PaymentCallbackJob;
use App\Models\ListOfTimeSlot;
use App\Models\TempData;
use App\Models\VehicleType;
use App\Models\VehicleTypeTranslation;
use App\Services\AdminFiscalizationAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PaymentCallbackSuccessAfterCanceledAdminEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_notifies_admin_when_success_arrives_on_canceled_temp_data(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite test schema does not widen temp_data.status CHECK to include canceled (MySQL ENUM migrations are guarded).');
        }

        $temp = $this->createCanceledTemp('tx-success-after-cancel-1');

        $mock = Mockery::mock(AdminFiscalizationAlertService::class);
        $mock->shouldReceive('notifyPaymentSuccessAfterCanceled')
            ->once()
            ->with(
                Mockery::on(function ($t) use ($temp): bool {
                    return $t instanceof TempData
                        && (int) $t->id === (int) $temp->id
                        && $t->status === TempData::STATUS_CANCELED;
                }),
                ['source' => 'test_incoming']
            );
        $this->app->instance(AdminFiscalizationAlertService::class, $mock);

        (new PaymentCallbackJob(
            ['merchant_transaction_id' => $temp->merchant_transaction_id, 'status' => 'success'],
            ['source' => 'test_incoming'],
        ))->handle();
    }

    private function createCanceledTemp(string $merchantTx): TempData
    {
        $drop = ListOfTimeSlot::query()->create(['time_slot' => '10:00 - 10:20']);
        $pick = ListOfTimeSlot::query()->create(['time_slot' => '11:00 - 11:20']);
        $vt = VehicleType::query()->create(['price' => 5]);
        foreach (['en', 'cg'] as $locale) {
            VehicleTypeTranslation::query()->create([
                'vehicle_type_id' => $vt->id,
                'locale' => $locale,
                'name' => 'Car',
                'description' => null,
            ]);
        }

        $temp = TempData::query()->create([
            'merchant_transaction_id' => $merchantTx,
            'drop_off_time_slot_id' => $drop->id,
            'pick_up_time_slot_id' => $pick->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'user_name' => 'Test User',
            'country' => 'ME',
            'license_plate' => 'ABC123',
            'vehicle_type_id' => $vt->id,
            'email' => 'guest@example.com',
            'status' => TempData::STATUS_CANCELED,
            'resolution_reason' => 'test_cancel',
        ]);

        return $temp->fresh();
    }
}
