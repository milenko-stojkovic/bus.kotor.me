@php
    $p = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('panel', $key, $fallback);
    $s = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('statistics', $key, $fallback);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $s('title', $p('page_statistics_title', 'Statistics')) }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm font-medium text-gray-500">{{ $s('total_paid', 'Total paid') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">
                        {{ $total_paid_formatted }} €
                    </p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm font-medium text-gray-500">{{ $s('number_of_visits', 'Number of visits') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $visit_count }}</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $s('vehicles_usage', 'Vehicles usage') }}</h3>

                    @if ($vehicle_usage->isEmpty())
                        <p class="text-sm text-gray-500">{{ $s('no_data', 'No data yet.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">{{ $s('registration_plates', 'Registration plates') }}</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">{{ $s('vehicle_category', 'Vehicle category') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-700">{{ $s('visits', 'Visits') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($vehicle_usage as $row)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-900">{{ $row['license_plate'] }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $row['category_label'] }}</td>
                                            <td class="px-3 py-2 text-right text-gray-900 tabular-nums">{{ $row['visits'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
