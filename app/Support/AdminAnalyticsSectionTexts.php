<?php

namespace App\Support;

final class AdminAnalyticsSectionTexts
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'kpi' => 'Brzi pregled ključnih pokazatelja za izabrani period.',
            'trend' => 'Dnevni pregled prihoda, rezervacija i zauzetih slotova.',
            'day_parts' => 'Kako su rezervacije i prihod raspoređeni kroz jutro, dan i veče. Prozor (jutro/dan/veče) se određuje po drop-off terminu, a prihod se računa samo za paid rezervacije; zato „Free jutro/veče“ može imati prihod ako je paid rezervacija svrstana u taj prozor.',
            'vehicle_types' => 'Pregled rezervacija, zauzetosti i prihoda po kategoriji vozila.',
            'agencies' => 'Pregled prihoda, rezervacija i iskorišćenja kapaciteta po registrovanim agencijama.',
            'admin_free_agencies' => 'Pregled besplatnih rezervacija koje su kreirali administratori (FZBR) grupisanih po agencijama.',
            'countries' => 'Pregled rezervacija i prihoda po državi (paid/free razdvojeno).',
            'paid_vs_free' => 'Odnos plaćenih i besplatnih rezervacija i njihov uticaj na zauzetost.',
            'blocking' => 'Kapacitet izgubljen zbog administrativnog blokiranja termina i dana.',
            'ops' => 'Operativni signali o problematičnim pokušajima plaćanja i fiskalnim kašnjenjima.',
        ];
    }
}

