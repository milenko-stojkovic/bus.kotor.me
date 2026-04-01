<!DOCTYPE html>
<html lang="sr-Latn-ME">
<head>
    <meta charset="utf-8">
    <title>Potvrda besplatne rezervacije</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            color: #333;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-cell {
            width: 80px;
        }
        .logo-cell img {
            height: 95px;
            max-width: 70px;
        }
        .header-center {
            text-align: center;
            padding-bottom: 16px;
            border-bottom: 2px solid #333;
        }
        .header-center h2 {
            margin: 0 0 6px 0;
            font-size: 16px;
        }
        .header-center p {
            margin: 0;
            font-size: 12px;
        }
        .free-banner {
            background-color: #7a1018;
            border: 2px solid #5a0c12;
            padding: 16px;
            margin: 18px 0;
            text-align: center;
            color: #fff;
        }
        .free-banner .title {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .free-banner .subtitle {
            font-size: 11px;
            font-weight: normal;
            margin: 0;
            opacity: 0.95;
        }
        .section {
            margin-bottom: 18px;
        }
        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 8px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table td {
            padding: 4px 0;
            vertical-align: top;
        }
        .data-table .label {
            font-weight: bold;
            width: 160px;
        }
        .total-box {
            font-size: 14px;
            font-weight: bold;
            margin-top: 18px;
            padding: 10px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
        }
        .total-amount {
            color: #7a1018;
            font-size: 16px;
        }
        .footer {
            margin-top: 32px;
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 16px;
        }
        .status-free {
            color: #7a1018;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if (! empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="Opština Kotor">
                @endif
            </td>
            <td class="header-center">
                <h2>OPŠTINA KOTOR</h2>
                <p>Stari grad 317, KOTOR</p>
            </td>
        </tr>
    </table>

    <div class="free-banner">
        <p class="title">POTVRDA BESPLATNE REZERVACIJE</p>
        <p class="subtitle">Ova rezervacija je besplatna za odabrane termine</p>
    </div>

    <div class="section">
        <div class="section-title">Podaci o rezervaciji</div>
        <table class="data-table">
            <tr>
                <td class="label">Broj rezervacije:</td>
                <td>{{ $reservation->id }}</td>
            </tr>
            <tr>
                <td class="label">Datum rezervacije:</td>
                <td>{{ $reservation->reservation_date->format('d.m.Y') }}</td>
            </tr>
            <tr>
                <td class="label">Status:</td>
                <td><span class="status-free">Besplatna rezervacija</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Podaci o korisniku</div>
        <table class="data-table">
            <tr>
                <td class="label">Naziv:</td>
                <td>{{ $reservation->user_name }}</td>
            </tr>
            <tr>
                <td class="label">Email:</td>
                <td>{{ $reservation->email }}</td>
            </tr>
            <tr>
                <td class="label">Država:</td>
                <td>{{ $countryDisplay }}</td>
            </tr>
            <tr>
                <td class="label">Registarske tablice:</td>
                <td>{{ $reservation->license_plate }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Detalji rezervacije</div>
        <table class="data-table">
            <tr>
                <td class="label">Tip vozila:</td>
                <td>{{ $vehicleTypeLabel }}</td>
            </tr>
            <tr>
                <td class="label">Vrijeme dolaska:</td>
                <td>{{ $reservation->dropOffTimeSlot->time_slot ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Vrijeme odlaska:</td>
                <td>{{ $reservation->pickUpTimeSlot->time_slot ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="total-box">
        <table class="data-table">
            <tr>
                <td class="label">Ukupan iznos:</td>
                <td><span class="total-amount">0,00 €</span></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Ova potvrda je automatski generisana od strane sistema Opštine Kotor.</p>
        <p>Za dodatne informacije kontaktirajte: bus@kotor.me</p>
        <p>Generisano: {{ now()->format('d.m.Y H:i:s') }}</p>
    </div>
</body>
</html>
