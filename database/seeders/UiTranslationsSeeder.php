<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UiTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
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

            // payment group (new keys for later UI)
            ['group' => 'payment', 'key' => 'payment_expired', 'locale' => 'cg', 'text' => 'Vrijeme za plaćanje je isteklo. Pokušajte ponovo.'],
            ['group' => 'payment', 'key' => 'payment_cancelled', 'locale' => 'cg', 'text' => 'Plaćanje je otkazano. Možete pokušati ponovo.'],
            ['group' => 'payment', 'key' => 'payment_insufficient_funds', 'locale' => 'cg', 'text' => 'Na kartici nema dovoljno sredstava.'],
            ['group' => 'payment', 'key' => 'payment_declined', 'locale' => 'cg', 'text' => 'Plaćanje je odbijeno. Provjerite podatke kartice ili pokušajte drugom karticom.'],
            ['group' => 'payment', 'key' => 'payment_card_check', 'locale' => 'cg', 'text' => 'Provjerite podatke kartice i pokušajte ponovo.'],
            ['group' => 'payment', 'key' => 'payment_authentication_failed', 'locale' => 'cg', 'text' => 'Potvrda plaćanja nije uspjela. Pokušajte ponovo.'],
            ['group' => 'payment', 'key' => 'payment_processing_issue', 'locale' => 'cg', 'text' => 'Došlo je do problema pri obradi plaćanja. Ako je potrebno, kontaktirajte podršku.'],
            ['group' => 'payment', 'key' => 'payment_try_again', 'locale' => 'cg', 'text' => 'Plaćanje nije uspelo. Pokušajte ponovo.'],
            ['group' => 'payment', 'key' => 'reservation_confirmed_fiscal_pending', 'locale' => 'cg', 'text' => 'Rezervacija je potvrđena. Fiskalni račun će biti poslat naknadno.'],
            ['group' => 'payment', 'key' => 'reservation_confirmed_invoice_later', 'locale' => 'cg', 'text' => 'Rezervacija je potvrđena. Račun će biti dostavljen naknadno.'],

            ['group' => 'payment', 'key' => 'payment_expired', 'locale' => 'en', 'text' => 'The payment session has expired. Please try again.'],
            ['group' => 'payment', 'key' => 'payment_cancelled', 'locale' => 'en', 'text' => 'The payment was cancelled. You can try again.'],
            ['group' => 'payment', 'key' => 'payment_insufficient_funds', 'locale' => 'en', 'text' => 'There are insufficient funds on the card.'],
            ['group' => 'payment', 'key' => 'payment_declined', 'locale' => 'en', 'text' => 'The payment was declined. Please check your card details or try another card.'],
            ['group' => 'payment', 'key' => 'payment_card_check', 'locale' => 'en', 'text' => 'Please check your card details and try again.'],
            ['group' => 'payment', 'key' => 'payment_authentication_failed', 'locale' => 'en', 'text' => 'Payment authentication failed. Please try again.'],
            ['group' => 'payment', 'key' => 'payment_processing_issue', 'locale' => 'en', 'text' => 'There was a problem processing the payment. If needed, please contact support.'],
            ['group' => 'payment', 'key' => 'payment_try_again', 'locale' => 'en', 'text' => 'The payment was not successful. Please try again.'],
            ['group' => 'payment', 'key' => 'reservation_confirmed_fiscal_pending', 'locale' => 'en', 'text' => 'The reservation is confirmed. The fiscal invoice will be sent later.'],
            ['group' => 'payment', 'key' => 'reservation_confirmed_invoice_later', 'locale' => 'en', 'text' => 'The reservation is confirmed. The invoice will be delivered later.'],
        ];

        $rows = array_map(function (array $row) use ($now): array {
            return [
                ...$row,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows);

        DB::table('ui_translations')->upsert(
            $rows,
            ['group', 'key', 'locale'],
            ['text', 'updated_at']
        );
    }
}