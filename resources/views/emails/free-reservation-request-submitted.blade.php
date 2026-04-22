@php
    $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y.') : '—';
    $slot = fn ($s) => $s ? ($s->time_slot ?? ('#'.$s->id)) : '—';
@endphp
<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.4;">
    <p><strong>Novi zahtjev za besplatnu rezervaciju (učenička/humanitarna).</strong></p>

    <p>
        <strong>Institucija/organizacija:</strong> {{ $r->institution_name }}<br>
        <strong>Email:</strong> {{ $r->institution_email }}<br>
        <strong>Telefon:</strong> {{ $r->institution_phone }}<br>
        <strong>Država:</strong> {{ $r->country }}<br>
        <strong>Locale:</strong> {{ $r->locale }}<br>
    </p>

    <p>
        <strong>Datum:</strong> {{ $fmt($r->reservation_date) }}<br>
        <strong>Vrijeme dolaska:</strong> {{ $slot($r->dropOffTimeSlot) }}<br>
        <strong>Vrijeme odlaska:</strong> {{ $slot($r->pickUpTimeSlot) }}<br>
    </p>

    <p><strong>Vozila:</strong></p>
    <ul>
        @foreach ($r->vehicles as $v)
            <li>
                {{ $v->license_plate }}
                —
                {{ $v->vehicleType?->getTranslatedName('cg') ?: ('#'.$v->vehicle_type_id) }}
                @php $desc = trim((string) ($v->vehicleType?->getTranslatedDescription('cg') ?? '')); @endphp
                @if ($desc !== '')
                    ({{ $desc }})
                @endif
            </li>
        @endforeach
    </ul>
</div>

