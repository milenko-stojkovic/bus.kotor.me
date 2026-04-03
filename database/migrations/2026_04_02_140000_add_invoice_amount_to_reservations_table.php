<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('invoice_amount', 10, 2)->default(0)->after('fiscal_date');
        });

        DB::table('reservations')->where('status', 'free')->update(['invoice_amount' => 0]);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $rows = DB::table('reservations')
                ->where('status', 'paid')
                ->get(['id', 'vehicle_type_id']);
            foreach ($rows as $row) {
                $price = $row->vehicle_type_id
                    ? (float) (DB::table('vehicle_types')->where('id', $row->vehicle_type_id)->value('price') ?? 0)
                    : 0;
                DB::table('reservations')->where('id', $row->id)->update([
                    'invoice_amount' => number_format($price, 2, '.', ''),
                ]);
            }
        } else {
            DB::statement('
                UPDATE reservations r
                INNER JOIN vehicle_types vt ON r.vehicle_type_id = vt.id
                SET r.invoice_amount = vt.price
                WHERE r.status = \'paid\'
            ');
            DB::table('reservations')
                ->where('status', 'paid')
                ->whereNull('vehicle_type_id')
                ->update(['invoice_amount' => 0]);
        }
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('invoice_amount');
        });
    }
};
