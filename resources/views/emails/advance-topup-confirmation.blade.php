@php
    /** @var \App\Models\AgencyAdvanceTopup $topup */
    /** @var \App\Models\User $agency */
    $locale = in_array($agency->lang ?? '', ['cg', 'en'], true) ? $agency->lang : 'cg';
    $referenceLine = \App\Support\ReservationEmailReferenceLine::forMerchantTransactionId(
        $topup->merchant_transaction_id,
        $locale,
    );
@endphp
Vaša avansna uplata je evidentirana. Potvrda se nalazi u prilogu.
@if ($referenceLine)

{{ $referenceLine }}
@endif
