@php
    /** @var \App\Models\User $agency */
    /** @var \App\Models\AgencyAdvanceTopup $topup */
    /** @var string $balanceAfter */

    $fmtMoney = fn ($v) => number_format((float) $v, 2, '.', '').' EUR';
@endphp
<!doctype html>
<html lang="sr-Latn-ME">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .logo { height: 54px; width: auto; }
        h1 { font-size: 16px; margin: 0; }
        .muted { color: #6b7280; font-size: 11px; margin-top: 2px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { text-align: left; color: #374151; font-size: 11px; }
        .right { text-align: right; }
        .note { margin-top: 10px; font-size: 11px; color: #374151; }
    </style>
</head>
<body>
    <div class="header">
        @if (!empty($logoDataUri))
            <img class="logo" src="{{ $logoDataUri }}" alt="Logo" />
        @endif
        <div>
            <h1>Potvrda o evidentiranoj avansnoj uplati</h1>
            <div class="muted">Datum evidentiranja: {{ $topup->paid_at?->format('d.m.Y. H:i') ?? '—' }}</div>
        </div>
    </div>

    <div class="card">
        <table>
            <tbody>
            <tr><td>Agencija / korisnik</td><td class="right">{{ $agency->name }}</td></tr>
            <tr><td>Email</td><td class="right">{{ $agency->email }}</td></tr>
            <tr><td>Iznos avansne uplate</td><td class="right"><strong>{{ $fmtMoney($topup->amount) }}</strong></td></tr>
            <tr><td>Merchant transaction ID</td><td class="right">{{ $topup->merchant_transaction_id }}</td></tr>
            <tr><td>Stanje avansa nakon evidentirane uplate</td><td class="right"><strong>{{ $fmtMoney($balanceAfter) }}</strong></td></tr>
            </tbody>
        </table>

        <div class="note">
            <strong>Napomena:</strong>
            Ovaj dokument ne predstavlja fiskalni račun. Fiskalni račun se izdaje prilikom realizacije pojedinačne usluge koja se plaća iz avansno uplaćenih sredstava.
        </div>
        <div class="note">
            Avansna sredstva se mogu koristiti isključivo za buduće usluge u okviru Bus Kotor servisa.
        </div>
    </div>
</body>
</html>

