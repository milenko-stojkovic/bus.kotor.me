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

            // landing (V2 predsoblje)
            ['group' => 'landing', 'key' => 'title', 'locale' => 'cg', 'text' => 'Rezervacija termina za turističke autobuse'],
            ['group' => 'landing', 'key' => 'title', 'locale' => 'en', 'text' => 'Tourist bus time-slot reservation'],
            ['group' => 'landing', 'key' => 'subtitle', 'locale' => 'cg', 'text' => 'Izaberite način nastavka.'],
            ['group' => 'landing', 'key' => 'subtitle', 'locale' => 'en', 'text' => 'Choose how you want to continue.'],
            ['group' => 'landing', 'key' => 'guest_title', 'locale' => 'cg', 'text' => 'Guest'],
            ['group' => 'landing', 'key' => 'guest_title', 'locale' => 'en', 'text' => 'Guest'],
            ['group' => 'landing', 'key' => 'guest_description', 'locale' => 'cg', 'text' => 'Za pojedinačne i povremene rezervacije.'],
            ['group' => 'landing', 'key' => 'guest_description', 'locale' => 'en', 'text' => 'For one-time and occasional reservations.'],
            ['group' => 'landing', 'key' => 'guest_cta', 'locale' => 'cg', 'text' => 'Nastavi kao guest'],
            ['group' => 'landing', 'key' => 'guest_cta', 'locale' => 'en', 'text' => 'Continue as guest'],
            ['group' => 'landing', 'key' => 'agency_title', 'locale' => 'cg', 'text' => 'Agencije'],
            ['group' => 'landing', 'key' => 'agency_title', 'locale' => 'en', 'text' => 'Agencies'],
            ['group' => 'landing', 'key' => 'agency_description', 'locale' => 'cg', 'text' => 'Za registrovane korisnike, bržu rezervaciju i pregled rezervacija.'],
            ['group' => 'landing', 'key' => 'agency_description', 'locale' => 'en', 'text' => 'For registered users, faster booking and reservation history.'],
            ['group' => 'landing', 'key' => 'agency_cta', 'locale' => 'cg', 'text' => 'Prijava za agencije'],
            ['group' => 'landing', 'key' => 'agency_cta', 'locale' => 'en', 'text' => 'Agency sign in'],

            // reservation (V2 guest reserve)
            ['group' => 'reservation', 'key' => 'title', 'locale' => 'cg', 'text' => 'Rezervacija (guest)'],
            ['group' => 'reservation', 'key' => 'title', 'locale' => 'en', 'text' => 'Reservation (guest)'],
            ['group' => 'reservation', 'key' => 'step_hint', 'locale' => 'cg', 'text' => 'Korak po korak: datum → dolazak → odlazak → podaci'],
            ['group' => 'reservation', 'key' => 'step_hint', 'locale' => 'en', 'text' => 'Step-by-step: date → arrival → departure → details'],
            ['group' => 'reservation', 'key' => 'date', 'locale' => 'cg', 'text' => 'Datum'],
            ['group' => 'reservation', 'key' => 'date', 'locale' => 'en', 'text' => 'Date'],
            ['group' => 'reservation', 'key' => 'arrival_time', 'locale' => 'cg', 'text' => 'Vrijeme dolaska'],
            ['group' => 'reservation', 'key' => 'arrival_time', 'locale' => 'en', 'text' => 'Arrival time'],
            ['group' => 'reservation', 'key' => 'departure_time', 'locale' => 'cg', 'text' => 'Vrijeme odlaska'],
            ['group' => 'reservation', 'key' => 'departure_time', 'locale' => 'en', 'text' => 'Departure time'],
            ['group' => 'reservation', 'key' => 'select_time_slot', 'locale' => 'cg', 'text' => 'Izaberite termin'],
            ['group' => 'reservation', 'key' => 'select_time_slot', 'locale' => 'en', 'text' => 'Select a time slot'],
            ['group' => 'reservation', 'key' => 'departure_disabled_hint', 'locale' => 'cg', 'text' => 'Vrijeme odlaska je dostupno tek nakon izbora vremena dolaska.'],
            ['group' => 'reservation', 'key' => 'departure_disabled_hint', 'locale' => 'en', 'text' => 'Departure is disabled until you choose arrival.'],
            ['group' => 'reservation', 'key' => 'vehicle_category', 'locale' => 'cg', 'text' => 'Kategorija vozila'],
            ['group' => 'reservation', 'key' => 'vehicle_category', 'locale' => 'en', 'text' => 'Vehicle category'],
            ['group' => 'reservation', 'key' => 'select_vehicle_category', 'locale' => 'cg', 'text' => 'Izaberite kategoriju'],
            ['group' => 'reservation', 'key' => 'select_vehicle_category', 'locale' => 'en', 'text' => 'Select category'],
            ['group' => 'reservation', 'key' => 'company_name', 'locale' => 'cg', 'text' => 'Naziv kompanije'],
            ['group' => 'reservation', 'key' => 'company_name', 'locale' => 'en', 'text' => 'Company name'],
            ['group' => 'reservation', 'key' => 'country', 'locale' => 'cg', 'text' => 'Država'],
            ['group' => 'reservation', 'key' => 'country', 'locale' => 'en', 'text' => 'Country'],
            ['group' => 'reservation', 'key' => 'select_country', 'locale' => 'cg', 'text' => 'Izaberite državu'],
            ['group' => 'reservation', 'key' => 'select_country', 'locale' => 'en', 'text' => 'Select country'],
            ['group' => 'reservation', 'key' => 'registration_plates', 'locale' => 'cg', 'text' => 'Registarske tablice'],
            ['group' => 'reservation', 'key' => 'registration_plates', 'locale' => 'en', 'text' => 'Registration plates'],
            ['group' => 'reservation', 'key' => 'email', 'locale' => 'cg', 'text' => 'Email'],
            ['group' => 'reservation', 'key' => 'email', 'locale' => 'en', 'text' => 'Email'],
            ['group' => 'reservation', 'key' => 'accept_terms', 'locale' => 'cg', 'text' => 'Saglasan/a sam sa uslovima korišćenja'],
            ['group' => 'reservation', 'key' => 'accept_terms', 'locale' => 'en', 'text' => 'I agree to the terms of service'],
            ['group' => 'reservation', 'key' => 'accept_privacy', 'locale' => 'cg', 'text' => 'Saglasan/a sam sa politikom privatnosti'],
            ['group' => 'reservation', 'key' => 'accept_privacy', 'locale' => 'en', 'text' => 'I agree to the privacy policy'],
            ['group' => 'reservation', 'key' => 'reserve', 'locale' => 'cg', 'text' => 'Rezerviši'],
            ['group' => 'reservation', 'key' => 'reserve', 'locale' => 'en', 'text' => 'Reserve'],
            ['group' => 'reservation', 'key' => 'free_reservation', 'locale' => 'cg', 'text' => 'Ova rezervacija je besplatna'],
            ['group' => 'reservation', 'key' => 'free_reservation', 'locale' => 'en', 'text' => 'This reservation is free'],
            ['group' => 'reservation', 'key' => 'total_to_pay', 'locale' => 'cg', 'text' => 'Ukupno za plaćanje'],
            ['group' => 'reservation', 'key' => 'total_to_pay', 'locale' => 'en', 'text' => 'Total to pay'],
            ['group' => 'reservation', 'key' => 'spots_left', 'locale' => 'cg', 'text' => 'slobodna mjesta'],
            ['group' => 'reservation', 'key' => 'spots_left', 'locale' => 'en', 'text' => 'spots left'],
            ['group' => 'reservation', 'key' => 'free_slot', 'locale' => 'cg', 'text' => 'besplatno'],
            ['group' => 'reservation', 'key' => 'free_slot', 'locale' => 'en', 'text' => 'free'],
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