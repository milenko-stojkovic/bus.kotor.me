<!DOCTYPE html>
<html lang="sr-Latn">
<head>
    <meta charset="utf-8">
    <title>Limo incident</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
<p>Poštovani,</p>
<p>Automatski izvještaj o Limo incidentu (sistem ne donosi sankcije; služi kao evidencija).</p>
<ul>
    <li><strong>Tip:</strong> {{ $typeLabelCg }} ({{ $incident->type }})</li>
    <li><strong>ID incidenta:</strong> {{ $incident->incident_uuid }}</li>
    <li><strong>Tablica (evidencija):</strong> {{ $incident->license_plate_snapshot ?? '—' }}</li>
    <li><strong>Vidljivo ime agencije (unos evidentra):</strong> {{ $incident->visible_agency_name ?? '—' }}</li>
    <li><strong>Poznata agencija (ako je unesena / pronađena):</strong>
        @if($incident->agency_name_snapshot)
            {{ $incident->agency_name_snapshot }} ({{ $incident->agency_email_snapshot ?? 'email nije unesen' }})
        @else
            —
        @endif
    </li>
    <li><strong>Vrijeme:</strong> {{ $occurredAtFormatted }}</li>
    <li><strong>GPS:</strong>
        @if($incident->gps_lat !== null && $incident->gps_lng !== null)
            {{ $incident->gps_lat }}, {{ $incident->gps_lng }}
        @else
            —
        @endif
    </li>
    <li><strong>Evidenter:</strong> {{ $evidenterLabel }}</li>
</ul>
@if($incident->note)
    <p><strong>Napomena evidentra:</strong><br>{{ $incident->note }}</p>
@endif
<p>U prilogu: fotografija tablice @if($incident->branding_photo_path) i fotografija brendinga @endif.</p>
</body>
</html>
