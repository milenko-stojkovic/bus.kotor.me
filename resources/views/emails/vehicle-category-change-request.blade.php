<p>Došao je zahtjev za promjenu kategorije vozila.</p>

<ul>
    <li>Agencija: <strong>{{ $agencyName }}</strong></li>
    <li>Email: <strong>{{ $agencyEmail }}</strong></li>
    <li>Registarska tablica: <strong>{{ $licensePlate }}</strong></li>
    <li>Stara kategorija: <strong>{{ $oldCategory }}</strong></li>
    <li>Tražena kategorija: <strong>{{ $requestedCategory }}</strong></li>
    <li>Broj priloga: <strong>{{ $attachmentCount }}</strong></li>
</ul>

@if ($adminReviewUrl !== '')
    <p>Pregled svih priloga i odluka: <a href="{{ $adminReviewUrl }}">{{ $adminReviewUrl }}</a></p>
@else
    <p>Molimo provjerite priložene dokumente i donesite odluku u Admin → Agencije.</p>
@endif
