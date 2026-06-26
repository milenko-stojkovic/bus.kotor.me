@php
    $block = session('guest_lower_category_block');
    if (! is_array($block)) {
        return;
    }
    $locale = ($block['locale'] ?? app()->getLocale()) === 'cg' ? 'cg' : 'en';
    $requiredCategory = (string) ($block['required_category'] ?? '');
    $intro = \App\Support\UiText::t(
        'booking',
        'guest_lower_category_block_intro',
        $locale === 'cg'
            ? 'Za ovu registarsku tablicu ranije je plaćena rezervacija u višoj kategoriji vozila.'
            : 'This license plate was previously used for a paid reservation in a higher vehicle category.',
        $locale,
    );
    $select = \App\Support\UiText::t(
        'booking',
        'guest_lower_category_block_select_category',
        $locale === 'cg'
            ? 'Da biste nastavili, vozilo prijavite u kategoriji:'
            : 'To continue, please select the category:',
        $locale,
    );
    $agency = \App\Support\UiText::t(
        'booking',
        'guest_lower_category_block_agency_note',
        $locale === 'cg'
            ? 'Ako rezervacije pravite kao agencija, registrujte se i koristite panel za agencije. U panelu za agencije možete upravljati vozilima i rješavati zahtjeve za promjenu kategorije.'
            : 'If you are making reservations as an agency, register and use the agency panel. In the agency panel, you can manage vehicles and resolve vehicle category change requests.',
        $locale,
    );
    $support = \App\Support\UiText::t(
        'booking',
        'guest_lower_category_block_support',
        $locale === 'cg'
            ? 'Za podršku pišite na bus@kotor.me.'
            : 'For support, contact bus@kotor.me.',
        $locale,
    );
    $guideLabel = \App\Support\UiText::t(
        'booking',
        'guest_lower_category_block_agency_guide',
        $locale === 'cg' ? 'Uputstvo za agencije' : 'Agency guide',
        $locale,
    );
    $guidePath = (string) config('user-guides.'.$locale, '');
    $guideHref = ($guidePath !== '' && is_file(public_path($guidePath))) ? asset($guidePath) : null;
    $linkClass = 'font-medium text-red-700 hover:text-red-600 underline decoration-red-200 underline-offset-2';
@endphp
<div class="rounded-md bg-red-50 p-4 text-sm text-red-900 space-y-3">
    <p class="m-0">{{ $intro }}</p>
    <p class="m-0"><span>{{ $select }}</span> <strong>{{ $requiredCategory }}</strong></p>
    <p class="m-0">{{ $agency }}</p>
    @if ($guideHref)
        <p class="m-0">
            <a href="{{ $guideHref }}" target="_blank" rel="noopener noreferrer" class="{{ $linkClass }}">{{ $guideLabel }}</a>
        </p>
    @endif
    <p class="m-0">{{ $support }}</p>
</div>
