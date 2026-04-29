<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('temp_data')) {
            return;
        }

        // Rebuild temp_data without SQLite CHECK constraint on status (enum).
        DB::statement('PRAGMA foreign_keys=off');
        DB::statement('BEGIN TRANSACTION');

        DB::statement('ALTER TABLE temp_data RENAME TO temp_data_old');

        DB::statement(<<<'SQL'
CREATE TABLE temp_data (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  merchant_transaction_id VARCHAR(64) NOT NULL,
  retry_token VARCHAR(36) NULL,
  user_id BIGINT NULL,
  vehicle_id INTEGER NULL,
  drop_off_time_slot_id INTEGER NOT NULL,
  pick_up_time_slot_id INTEGER NOT NULL,
  reservation_date DATE NOT NULL,
  user_name VARCHAR(255) NOT NULL,
  country VARCHAR(100) NOT NULL,
  license_plate VARCHAR(50) NOT NULL,
  vehicle_type_id INTEGER NOT NULL,
  email VARCHAR(255) NOT NULL,
  preferred_locale VARCHAR(5) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  raw_callback_payload TEXT NULL,
  callback_error_code VARCHAR(64) NULL,
  callback_error_reason TEXT NULL,
  resolution_reason VARCHAR(64) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
SQL);

        DB::statement(<<<'SQL'
INSERT INTO temp_data (
  id, merchant_transaction_id, retry_token, user_id, vehicle_id,
  drop_off_time_slot_id, pick_up_time_slot_id, reservation_date,
  user_name, country, license_plate, vehicle_type_id, email, preferred_locale,
  status, raw_callback_payload, callback_error_code, callback_error_reason, resolution_reason,
  created_at, updated_at
)
SELECT
  id, merchant_transaction_id, retry_token, user_id, vehicle_id,
  drop_off_time_slot_id, pick_up_time_slot_id, reservation_date,
  user_name, country, license_plate, vehicle_type_id, email, preferred_locale,
  status, raw_callback_payload, callback_error_code, callback_error_reason, resolution_reason,
  created_at, updated_at
FROM temp_data_old;
SQL);

        DB::statement('DROP TABLE temp_data_old');

        // Recreate indexes/uniques used by the app.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_temp_merchant_tx ON temp_data (merchant_transaction_id)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_temp_retry_token ON temp_data (retry_token)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_temp_date ON temp_data (reservation_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_temp_status ON temp_data (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_temp_vehicle ON temp_data (vehicle_type_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_temp_merchant_tx ON temp_data (merchant_transaction_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_temp_plate_date ON temp_data (license_plate, reservation_date)');

        DB::statement('COMMIT');
        DB::statement('PRAGMA foreign_keys=on');
    }

    public function down(): void
    {
        // No-op: SQLite-only test helper migration.
    }
};

