<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportEmailsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('report_emails')->insertOrIgnore([
            ['email' => 'informatika@kotor.me', 'created_at' => '2025-07-01 00:33:14'],
            ['email' => 'prihodi@kotor.me', 'created_at' => '2025-07-01 08:10:26'],
            ['email' => 'ksenija.prorokovic@kotor.me', 'created_at' => '2026-01-15 08:58:48'],
        ]);
    }
}