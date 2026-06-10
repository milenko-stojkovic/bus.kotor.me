@php
    $p = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('panel', $key, $fallback);
    $locale = app()->getLocale();
    $pickupMapUrl1 = 'https://maps.app.goo.gl/BkmeQ1ZAo8XG1FDb6';
    $pickupMapUrl2 = 'https://maps.app.goo.gl/qdy9YmTLRBsggPCD6';

    $pickupLinkText1 = $p(
        'limo_notice_pickup_place_1',
        $locale === 'en'
            ? 'the bus stop by the city market'
            : 'autobuskom stajalištu kod gradske pijace',
    );
    $pickupLinkText2 = $p(
        'limo_notice_pickup_place_2',
        $locale === 'en'
            ? 'the bus stop near the extension of the parking area'
            : 'autobuskom stajalištu u produžetku parkinga',
    );

    $pickupLinkHtml1 = '<a href="' . e($pickupMapUrl1) . '" target="_blank" rel="noopener noreferrer" class="font-medium text-red-700 hover:text-red-600 underline decoration-red-200 underline-offset-2">' . e($pickupLinkText1) . '</a>';
    $pickupLinkHtml2 = '<a href="' . e($pickupMapUrl2) . '" target="_blank" rel="noopener noreferrer" class="font-medium text-red-700 hover:text-red-600 underline decoration-red-200 underline-offset-2">' . e($pickupLinkText2) . '</a>';

    $p1Escaped = e($p(
        'limo_info_pickup',
        $locale === 'en'
            ? 'Passenger pickup for limo service in the Old Town zone is allowed only at the approved locations — :pickup_link_1 and :pickup_link_2.'
            : 'Ukrcavanje putnika za limo uslugu u zoni Starog grada dozvoljeno je samo na odobrenim mjestima — :pickup_link_1 i :pickup_link_2.',
    ));
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $p('nav_limo', 'Limo') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4 text-sm text-gray-700">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ $p('limo_info_title', $locale === 'en' ? 'Limo service in the Old Town' : 'Limo usluga u Starom gradu') }}
                    </h3>

                    <p>
                        {{ $p(
                            'limo_info_intro',
                            $locale === 'en'
                                ? 'This page provides operational information for agencies whose vehicles perform limo (pick-up) service in the Old Town zone.'
                                : 'Ova stranica sadrži operativne informacije za agencije čija vozila pružaju limo (ukrcaj) uslugu u zoni Starog grada.',
                        ) }}
                    </p>

                    <p>
                        {!! str_replace([':pickup_link_1', ':pickup_link_2'], [$pickupLinkHtml1, $pickupLinkHtml2], $p1Escaped) !!}
                    </p>

                    <p>
                        {{ $p(
                            'limo_info_daily_fee',
                            $locale === 'en'
                                ? 'To operate legally on the selected day, the vehicle must have a valid Daily fee purchased through Reservations (paid by card or from advance, if enabled). The Daily fee is the operative permit for flexible pick-up and drop-off at Autoboka and Puč on that calendar day.'
                                : 'Da bi usluga bila u skladu sa pravilima za odabrani dan, vozilo mora imati važeću dnevnu naknadu kupljenu kroz Rezervacije (plaćanje karticom ili iz avansa, ako je uključeno). Dnevna naknada je operativna dozvola za fleksibilan ukrcaj i iskrcaj na Autoboki i Puču tog kalendarskog dana.',
                        ) }}
                    </p>

                    <p>
                        {{ $p(
                            'limo_info_control',
                            $locale === 'en'
                                ? 'Compliance is checked by municipal police and other authorised controllers. Keep the Daily fee confirmation (email/PDF) and follow the approved pickup rules.'
                                : 'Kontrolu vrši komunalna policija i druga ovlašćena lica. Držite potvrdu o dnevnoj naknadi (email/PDF) i poštujte pravila odobrenih mjesta ukrcaja.',
                        ) }}
                    </p>

                    <p class="text-gray-600">
                        {{ $p(
                            'limo_info_reservations_link',
                            $locale === 'en'
                                ? 'Purchase or review Daily fees under Reservations in the menu.'
                                : 'Dnevnu naknadu kupujte ili pregledajte u meniju Rezervacije.',
                        ) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
