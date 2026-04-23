@php
    /** @var \App\Models\FreeReservationRequest $req */
@endphp

<h2>Zahtjev za besplatnu rezervaciju (FZBR)</h2>

<p>Stigao je novi zahtjev iz agencijskog panela.</p>

<p>
    <strong>Podnosilac (nalog):</strong>
    {{ $req->institution_name }} ({{ $req->institution_email }})
</p>

<p>
    <strong>Datum:</strong> {{ $req->reservation_date?->format('d.m.Y.') ?? '—' }}<br>
    <strong>Vrijeme dolaska:</strong> {{ $req->dropOffTimeSlot?->time_slot ?? '—' }}<br>
    <strong>Vrijeme odlaska:</strong> {{ $req->pickUpTimeSlot?->time_slot ?? '—' }}
</p>

<p><strong>Vozila:</strong></p>
<ul>
    @foreach ($req->vehicles as $v)
        <li>
            {{ $v->license_plate }}
            — {{ $v->vehicle_type_label ?: ('#'.$v->vehicle_type_id) }}
        </li>
    @endforeach
</ul>

@if ($req->attachments->count() > 0)
    <p><strong>Dokumentacija:</strong> priložena u ovom email-u ({{ $req->attachments->count() }} fajl(a)).</p>
@endif

