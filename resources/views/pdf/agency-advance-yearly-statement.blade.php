@php
    /** @var \App\Models\User $agency */
    /** @var int $year */
    /** @var float $openingBalance */
    /** @var list<array{date:string,type:string,description:string,amount:float,balance_after:float}> $rows */
    /** @var array{topup_total:float,usage_total:float,correction_total:float} $totals */
    /** @var float $closingBalance */

    $fmtMoney = fn (float $v) => number_format($v, 2, '.', '').' EUR';
    $fmtSigned = function (float $v) use ($fmtMoney): string {
        $sign = $v > 0.000001 ? '+' : '';
        return $sign.$fmtMoney($v);
    };
    $typeLabel = fn (string $t) => match ($t) {
        'topup' => 'Uplata',
        'usage' => 'Iskorišćeno',
        'correction' => 'Korekcija',
        default => $t,
    };
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
            <h1>Kartica avansa za godinu {{ $year }}</h1>
            <div class="muted">Period: 01.01.{{ $year }} – 31.12.{{ $year }}</div>
        </div>
    </div>

    <div class="card">
        <table>
            <tbody>
            <tr><td>Agencija</td><td class="right">{{ $agency->name }}</td></tr>
            <tr><td>Email</td><td class="right">{{ $agency->email }}</td></tr>
            <tr><td>Godina</td><td class="right">{{ $year }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <table>
            <tbody>
            <tr><td><strong>Početno stanje (01.01.{{ $year }})</strong></td><td class="right"><strong>{{ $fmtSigned($openingBalance) }}</strong></td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div><strong>Hronologija</strong></div>
        @if (empty($rows))
            <div class="muted" style="margin-top: 6px">Nema transakcija u izabranoj godini.</div>
        @else
            <table>
                <thead>
                <tr>
                    <th>Datum</th>
                    <th>Tip</th>
                    <th>Opis</th>
                    <th class="right">Iznos</th>
                    <th class="right">Stanje nakon</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $typeLabel($row['type']) }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="right">{{ $fmtSigned((float)$row['amount']) }}</td>
                        <td class="right">{{ $fmtSigned((float)$row['balance_after']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <div><strong>Ukupno u godini</strong></div>
        <table>
            <tbody>
            <tr><td>Ukupno uplaćeno</td><td class="right">{{ $fmtMoney((float)($totals['topup_total'] ?? 0)) }}</td></tr>
            <tr><td>Ukupno iskorišćeno</td><td class="right">{{ $fmtMoney((float)($totals['usage_total'] ?? 0)) }}</td></tr>
            <tr><td>Ukupno korekcija</td><td class="right">{{ $fmtSigned((float)($totals['correction_total'] ?? 0)) }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <table>
            <tbody>
            <tr><td><strong>Završno stanje (31.12.{{ $year }})</strong></td><td class="right"><strong>{{ $fmtSigned($closingBalance) }}</strong></td></tr>
            </tbody>
        </table>
        <div class="note">
            Ovaj dokument predstavlja pregled avansnih uplata i njihovog korišćenja i ne predstavlja fiskalni račun.
        </div>
    </div>
</body>
</html>

