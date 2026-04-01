<!DOCTYPE html>
<html lang="sr-Latn-ME">
<head>
    <meta charset="UTF-8">
    <title>Račun</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 14px; color: #000; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .upper { text-transform: uppercase; }
        .header { margin-bottom: 8px; }
        .line { border-bottom: 1px dashed #000; margin: 6px 0; }
        .table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .table th, .table td { padding: 2px 4px; text-align: left; }
        .table th { border-bottom: 1px solid #000; }
        .footer { margin-top: 15px; font-size: 12px; }
        .qr { text-align: center; margin: 6px 0 3px 0; }
        .small { font-size: 11px; }
        .totals-table { width: 100%; font-size: 11px; margin-top: 4px; }
        .totals-table td { padding: 2px 0; }
        .totals-table td:last-child { text-align: right; }
    </style>
</head>
<body>
    <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
        <tr>
            <td style="width:70px; vertical-align:top;">
                @if (! empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="Opština Kotor" style="height:95px;">
                @endif
            </td>
            <td style="text-align:center; vertical-align:top;">
                <div class="header center upper bold">
                    BEZGOTOVINSKI RAČUN<br>
                    OPŠTINA KOTOR<br>
                    Stari grad 317, KOTOR
                    <p>
                        <span class="small">
                            PIB: 02012936<br>
                            PDV broj: 92/31 02634 4
                        </span>
                    </p>
                </div>
            </td>
        </tr>
    </table>

    <div class="line"></div>
    <div class="center bold" style="margin-bottom:5px;">
        NAKNADA ZA ISKORIŠĆAVANJE KULTURNIH DOBARA
    </div>
    <div class="small">
        Račun: <span class="bold">{{ $reservation->merchant_transaction_id }}</span><br>
        Vrijeme: <span class="bold">{{ $fiscalDateTime->format('d.m.Y H:i') }}</span><br>
        Izdato: <span class="bold">OPŠTINA KOTOR</span>
    </div>
    <div class="line"></div>
    <table class="table small">
        <tr>
            <th>Naziv</th>
            <th>Cijena</th>
            <th>Količina</th>
            <th>Porez</th>
            <th>Ukupno</th>
        </tr>
        <tr>
            <td>{{ $vehicleLine }}</td>
            <td>{{ number_format($unitPrice, 2, '.', '') }}</td>
            <td>1</td>
            <td>0,00</td>
            <td>{{ number_format($unitPrice, 2, '.', '') }}</td>
        </tr>
    </table>
    <table class="totals-table small">
        <tr>
            <td>Ukupan iznos:</td>
            <td>{{ number_format($unitPrice, 2, '.', '') }}EUR</td>
        </tr>
        <tr>
            <td>Ukupno:</td>
            <td>{{ number_format($unitPrice, 2, '.', '') }}EUR</td>
        </tr>
    </table>
    <div class="small" style="margin-top:4px;">
        Oslobođenje (oslobođeno od javnog interesa, čl.26 Zakon o PDV-u)
    </div>
    <div class="line"></div>

    @if ($isFiscal)
        <div class="small">
            IKOF: <span class="bold">{{ $reservation->fiscal_ikof ?? '—' }}</span><br>
            JIKR: <span class="bold">{{ $reservation->fiscal_jir ?? '—' }}</span><br>
            @if (! empty($internalNumber))
                Interni broj: <span class="bold">{{ $internalNumber }}</span>
            @endif
        </div>
        <div class="qr">
            @if (! empty($qrDataUri))
                <img src="{{ $qrDataUri }}" style="width:110px; height:110px;" alt="">
            @endif
        </div>
    @else
        <div class="small bold" style="margin:8px 0;">
            {{ $nonFiscalNote }}
        </div>
    @endif

    <div class="footer center small" style="margin-top:8px;">
        www.primatech.me<br>
        <span style="font-size:10px;">
            @if ($isFiscal)
                Ovaj račun je generisan automatski i važi kao fiskalni dokument.
            @else
                Ova potvrda je automatski generisana od strane sistema Opštine Kotor.
            @endif
        </span>
    </div>
    <div class="line" style="margin-top:8px;"></div>

    <div style="margin-top:8px;">
        <div class="bold" style="font-size:13px; margin-bottom:4px; border-bottom:1px dashed #000; padding-bottom:2px;">
            Podaci o korisniku
        </div>
        <div class="small" style="margin-bottom:2px;">
            <span class="bold">Naziv kompanije:</span> {{ $reservation->user_name ?? 'N/A' }}
        </div>
        <div class="small" style="margin-bottom:2px;">
            <span class="bold">Email:</span> {{ $reservation->email ?? 'N/A' }}
        </div>
        <div class="small" style="margin-bottom:2px;">
            <span class="bold">Država:</span> {{ $countryDisplay }}
        </div>
        <div class="small" style="margin-bottom:4px;">
            <span class="bold">Registarske tablice:</span> {{ $reservation->license_plate ?? 'N/A' }}
        </div>
    </div>
    <div class="line"></div>

    <div style="margin-top:8px;">
        <div class="bold" style="font-size:13px; margin-bottom:4px; border-bottom:1px dashed #000; padding-bottom:2px;">
            Detalji rezervacije
        </div>
        <div class="small" style="margin-bottom:2px;">
            <span class="bold">Tip vozila:</span> {{ $vehicleLine }}
        </div>
        <div class="small" style="margin-bottom:2px;">
            <span class="bold">Datum rezervacije:</span> {{ $reservation->reservation_date->format('d.m.Y') }}
        </div>
        <div class="small" style="margin-bottom:2px;">
            <span class="bold">Vrijeme dolaska:</span> {{ $reservation->dropOffTimeSlot->time_slot ?? 'N/A' }}
        </div>
        <div class="small" style="margin-bottom:4px;">
            <span class="bold">Vrijeme odlaska:</span> {{ $reservation->pickUpTimeSlot->time_slot ?? 'N/A' }}
        </div>
    </div>
</body>
</html>
