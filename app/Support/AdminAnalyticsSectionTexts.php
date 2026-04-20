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
            'day_parts' => 'Kako su rezervacije i prihod raspoređeni kroz jutro, dan i veče.',
            'vehicle_types' => 'Pregled rezervacija, zauzetosti i prihoda po kategoriji vozila.',
            'countries' => 'Pregled rezervacija i prihoda po državi (paid/free razdvojeno).',
            'paid_vs_free' => 'Odnos plaćenih i besplatnih rezervacija i njihov uticaj na zauzetost.',
            'blocking' => 'Kapacitet izgubljen zbog administrativnog blokiranja termina i dana.',
            'ops' => 'Operativni signali o problematičnim pokušajima plaćanja i fiskalnim kašnjenjima.',
        ];
    }
}

