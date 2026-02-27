<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Http;

/**
 * Fiskalizacija rezervacije. Vraća ['fiscal_jir' => ..., ...] na uspeh ili ['error' => message] na neuspeh.
 * Driver iz config('services.fiscalization.driver'): fake = POST na FakeFiscalizationController, real = pravi API.
 */
class FiscalizationService
{
    /**
     * Poziv fiskalnog API-ja. Na uspeh vraća niz sa fiscal_jir (i ostalim poljima); na neuspeh ['error' => string].
     */
    public function tryFiscalize(Reservation $reservation): array
    {
        $driver = config('services.fiscalization.driver', 'fake');

        if ($driver === 'fake') {
            return $this->callFakeFiscalization($reservation);
        }

        return $this->callRealFiscalization($reservation);
    }

    /**
     * POST na naš fake endpoint (isti payload kao za real servis).
     */
    private function callFakeFiscalization(Reservation $reservation): array
    {
        $payload = $this->buildFiscalPayload($reservation);
        $url = url(route('api.fake-fiscalization'));

        $response = Http::acceptJson()
            ->contentType('application/json')
            ->timeout(15)
            ->post($url, $payload);

        if (! $response->successful()) {
            $body = $response->json();
            return ['error' => $body['message'] ?? $response->reason() ?? 'Fiscal service error'];
        }

        $data = $response->json();
        if (($data['status'] ?? '') !== 'OK') {
            return ['error' => $data['message'] ?? 'Fiscalization failed'];
        }

        return [
            'fiscal_jir' => $data['jir'] ?? 'FAKE-JIR-'.uniqid(),
            'fiscal_ikof' => $data['ikof'] ?? 'FAKE-IKOF-'.uniqid(),
            'fiscal_qr' => $data['qr'] ?? null,
            'fiscal_operator' => $data['operator'] ?? config('app.name'),
            'fiscal_date' => isset($data['fiscal_date']) ? \Carbon\Carbon::parse($data['fiscal_date']) : now(),
        ];
    }

    /**
     * Real fiskalni servis (trenutno stub; kasnije HTTP na config URL).
     */
    private function callRealFiscalization(Reservation $reservation): array
    {
        // TODO: HTTP POST na config('services.fiscalization.url') sa buildFiscalPayload($reservation)
        return [
            'fiscal_jir' => 'JIR-'.uniqid(),
            'fiscal_ikof' => 'IKOF-'.uniqid(),
            'fiscal_qr' => null,
            'fiscal_operator' => config('app.name'),
            'fiscal_date' => now(),
        ];
    }

    /**
     * Payload identičan onom koji se šalje realnom servisu (za fake i real).
     */
    private function buildFiscalPayload(Reservation $reservation): array
    {
        return [
            'reservation_id' => $reservation->id,
            'merchant_transaction_id' => $reservation->merchant_transaction_id,
            'reservation_date' => $reservation->reservation_date?->format('Y-m-d'),
            'user_name' => $reservation->user_name,
            'email' => $reservation->email,
            'license_plate' => $reservation->license_plate,
            'country' => $reservation->country,
        ];
    }
}
