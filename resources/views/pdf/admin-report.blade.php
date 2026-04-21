@php
    $fmtMoney = fn (float $v) => number_format($v, 2, '.', '').' EUR';
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
        .subtitle { color: #374151; margin-top: 3px; }
        .muted { color: #6b7280; font-size: 11px; margin-top: 2px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; }
        th { text-align: left; color: #374151; font-size: 11px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        @if (!empty($logoDataUri))
            <img class="logo" src="{{ $logoDataUri }}" alt="Logo" />
        @endif
        <div>
            <h1>{{ (string)($dataset['title'] ?? 'Izvještaj') }}</h1>
            <div class="subtitle">{{ (string)($dataset['subtitle'] ?? '') }}</div>
            <div class="muted">Period: {{ (string)($dataset['period'] ?? '') }}</div>
        </div>
    </div>

    @php($kind = (string)($dataset['kind'] ?? ''))
    @php($data = (array)($dataset['data'] ?? []))

    @if ($kind === 'by_payment')
        <div class="card">
            <table>
                <tbody>
                <tr><td>Ukupan prihod</td><td class="right">{{ $fmtMoney((float)($data['revenue_eur'] ?? 0)) }}</td></tr>
                <tr><td>Broj transakcija</td><td class="right">{{ (int)($data['transactions'] ?? 0) }}</td></tr>
                </tbody>
            </table>
        </div>
    @elseif ($kind === 'by_realization')
        <div class="card">
            <table>
                <tbody>
                <tr><td>Ukupan prihod</td><td class="right">{{ $fmtMoney((float)($data['revenue_eur'] ?? 0)) }}</td></tr>
                <tr><td>Broj realizovanih rezervacija</td><td class="right">{{ (int)($data['realized_count'] ?? 0) }}</td></tr>
                </tbody>
            </table>
        </div>
    @elseif ($kind === 'by_vehicle_type')
        <div class="card">
            <table>
                <thead>
                <tr>
                    <th>Tip vozila</th>
                    <th class="right">Broj vozila</th>
                </tr>
                </thead>
                <tbody>
                @foreach ((array)($data['rows'] ?? []) as $row)
                    <tr>
                        <td>{{ (string)($row['label'] ?? '') }}</td>
                        <td class="right">{{ (int)($row['count'] ?? 0) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Ukupno</strong></td>
                    <td class="right"><strong>{{ (int)($data['total'] ?? 0) }}</strong></td>
                </tr>
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>

