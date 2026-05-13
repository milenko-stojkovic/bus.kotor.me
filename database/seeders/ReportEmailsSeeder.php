<?php

namespace Database\Seeders;

use App\Models\ReportEmail;
use Illuminate\Database\Seeder;

class ReportEmailsSeeder extends Seeder
{
    public function run(): void
    {
        $reportRows = [
            ['email' => 'informatika@kotor.me', 'created_at' => '2025-07-01 00:33:14'],
            ['email' => 'prihodi@kotor.me', 'created_at' => '2025-07-01 08:10:26'],
            ['email' => 'ksenija.prorokovic@kotor.me', 'created_at' => '2026-01-15 08:58:48'],
        ];

        foreach ($reportRows as $row) {
            ReportEmail::query()->firstOrCreate(
                [
                    'email' => $row['email'],
                    'purpose' => ReportEmail::PURPOSE_REPORT,
                ],
                [
                    'created_at' => $row['created_at'],
                ],
            );
        }

        ReportEmail::query()->updateOrCreate(
            [
                'email' => 'komunalna.policija@kotor.me',
                'purpose' => ReportEmail::PURPOSE_LIMO_INCIDENTS,
            ],
            [],
        );
    }
}
