@php
    $p = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('panel', $key, $fallback);
    /** @var \App\Models\LimoQrToken $token */
    $qrDataUri = $qrDataUri ?? '';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $p('limo_qr_title', 'Limo QR') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-6">
                <div class="flex justify-center">
                    <img src="{{ $qrDataUri }}" alt="QR" class="max-w-xs w-full h-auto border border-gray-200 rounded" />
                </div>
                <div class="flex flex-wrap gap-3 justify-center">
                    <x-secondary-button type="button" disabled class="opacity-60 cursor-not-allowed" title="{{ $p('limo_pdf_stub', 'PDF uskoro') }}">
                        {{ $p('limo_download_pdf', 'Preuzmi PDF') }}
                    </x-secondary-button>
                    <a href="{{ route('panel.limo.index', [], false) }}">
                        <x-secondary-button type="button">{{ $p('limo_back', 'Nazad na listu') }}</x-secondary-button>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
