@php
    /** @var array{date:string,total:int} $dataset */
    $dataset = $dataset ?? ['date' => '', 'total' => 0];
    $title = $title ?? 'Dnevne naknade';

    $dateLabel = (string) ($dataset['date'] ?? '');
    if ($dateLabel !== '') {
        try {
            $dateLabel = \Carbon\Carbon::parse($dateLabel)->format('d.m.Y.');
        } catch (\Throwable) {
            // keep raw label
        }
    }
@endphp

<section class="bg-white shadow rounded-lg p-4 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-gray-900">{{ $title }}</h2>
            <div class="text-sm text-gray-600">
                Datum: <span class="font-medium">{{ $dateLabel }}</span>
            </div>
        </div>
        <div class="text-xs text-gray-500 text-right">
            <span class="text-gray-600">Plaćene dnevne naknade</span>
        </div>
    </div>

    <div class="mt-4 flex items-baseline gap-2">
        <span class="text-4xl font-semibold tabular-nums text-gray-900">{{ (int) ($dataset['total'] ?? 0) }}</span>
        <span class="text-sm text-gray-600">rezervacija</span>
    </div>
</section>
