@php
    $p = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('panel', $key, $fallback);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $p('page_statistics_title', 'Statistic') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-6 bg-white shadow sm:rounded-lg text-gray-600">
                {{ $p('page_statistics_placeholder', 'Content coming soon.') }}
            </div>
        </div>
    </div>
</x-app-layout>
