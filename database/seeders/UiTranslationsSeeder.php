<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UiTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('ui_translations')->insert([
            ['group' => 'buttons', 'key' => 'pay_now', 'locale' => 'en', 'text' => 'Pay now'],
            ['group' => 'buttons', 'key' => 'pay_now', 'locale' => 'cg', 'text' => 'Plati sada'],
            ['group' => 'checkout', 'key' => 'slot_unavailable', 'locale' => 'en', 'text' => 'Selected time slot is no longer available.'],
            ['group' => 'checkout', 'key' => 'slot_unavailable', 'locale' => 'cg', 'text' => 'Izabrani termin više nije dostupan.'],
            ['group' => 'checkout', 'key' => 'payment_pending', 'locale' => 'en', 'text' => 'Your payment is being processed.'],
            ['group' => 'checkout', 'key' => 'payment_pending', 'locale' => 'cg', 'text' => 'Vaše plaćanje je u obradi.'],
            ['group' => 'errors', 'key' => 'payment_failed', 'locale' => 'en', 'text' => 'Payment failed. Please try again.'],
            ['group' => 'errors', 'key' => 'payment_failed', 'locale' => 'cg', 'text' => 'Plaćanje nije uspjelo. Pokušajte ponovo.'],
            ['group' => 'emails', 'key' => 'reservation_subject', 'locale' => 'en', 'text' => 'Your reservation confirmation'],
            ['group' => 'emails', 'key' => 'reservation_subject', 'locale' => 'cg', 'text' => 'Potvrda vaše rezervacije'],
        ]);
    }
}