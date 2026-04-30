@php
    $d = $dataset ?? [];
    $fmtMoney = fn (float $v) => number_format($v, 2, '.', '').' EUR';
    $t = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('statistics', $key, $fallback);

    $from = (string) ($d['date_from'] ?? '');
    $to = (string) ($d['date_to'] ?? '');
    $period = $from !== '' && $to !== '' ? ($from.' — '.$to) : '—';

    $agency = (string) ($d['agency_name'] ?? '');
    $totalPaid = (float) ($d['total_paid'] ?? 0);
    $visitCount = (int) ($d['visit_count'] ?? 0);
    $rows = $d['vehicle_usage'] ?? [];
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
            <h1>{{ $t('pdf_title', 'Statistika agencije') }}</h1>
            @if ($agency !== '')
                <div class="subtitle">{{ $agency }}</div>
            @endif
            <div class="muted">{{ $t('period_label', 'Period') }}: {{ $period }}</div>
        </div>
    </div>

    <div class="card">
        <table>
            <tbody>
                <tr>
                    <td>{{ $t('total_paid', 'Ukupno plaćeno') }}</td>
                    <td class="right"><strong>{{ $fmtMoney($totalPaid) }}</strong></td>
                </tr>
                <tr>
                    <td>{{ $t('number_of_visits', 'Broj posjeta') }}</td>
                    <td class="right"><strong>{{ $visitCount }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div style="font-weight: 600; margin-bottom: 4px;">{{ $t('pdf_vehicle_table_title', 'Tabela po vozilima') }}</div>

        @if (empty($rows) || (is_object($rows) && method_exists($rows, 'isEmpty') && $rows->isEmpty()))
            <div class="muted">{{ $t('pdf_no_data_for_period', 'Nema podataka za izabrani period.') }}</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ $t('registration_plates', 'Registarske tablice') }}</th>
                        <th>{{ $t('vehicle_category', 'Kategorija vozila') }}</th>
                        <th class="right">{{ $t('visits', 'Posjete') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ (string)($row['license_plate'] ?? '') }}</td>
                            <td>{{ (string)($row['category_label'] ?? '') }}</td>
                            <td class="right">{{ (int)($row['visits'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>

