<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: dropForeign sa imenima nije pouzdan kao na MySQL-u; kolona dovoljna za test šemu.
            if (! Schema::hasColumn('temp_data', 'user_id')) {
                Schema::table('temp_data', function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('merchant_transaction_id');
                });
            }

            return;
        }

        Schema::table('temp_data', function (Blueprint $table) {
            if (! Schema::hasColumn('temp_data', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('merchant_transaction_id');
                $table->foreign('user_id', 'fk_temp_user')
                    ->references('id')->on('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('temp_data', function (Blueprint $table) {
                if (Schema::hasColumn('temp_data', 'user_id')) {
                    $table->dropColumn('user_id');
                }
            });

            return;
        }

        Schema::table('temp_data', function (Blueprint $table) {
            if (Schema::hasColumn('temp_data', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
