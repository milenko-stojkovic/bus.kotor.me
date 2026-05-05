Poštovani,

Automatski izvještaj o Limo incidentu (sistem ne donosi sankcije; služi kao evidencija).

Tip: {{ $typeLabelCg }} ({{ $incident->type }})
ID incidenta: {{ $incident->incident_uuid }}
Tablica (evidencija): {{ $incident->license_plate_snapshot ?? '—' }}
Vidljivo ime agencije (unos evidentra): {{ $incident->visible_agency_name ?? '—' }}
Poznata agencija (ako je unesena / pronađena): @if($incident->agency_name_snapshot){{ $incident->agency_name_snapshot }} ({{ $incident->agency_email_snapshot ?? 'email nije unesen' }})@else—@endif
Vrijeme: {{ $occurredAtFormatted }}
GPS: @if($incident->gps_lat !== null && $incident->gps_lng !== null){{ $incident->gps_lat }}, {{ $incident->gps_lng }}@else—@endif
Evidenter: {{ $evidenterLabel }}
@if($incident->note)

Napomena evidentra:
{{ $incident->note }}
@endif

U prilogu: fotografija tablice @if($incident->branding_photo_path)i fotografija brendinga @endif.
