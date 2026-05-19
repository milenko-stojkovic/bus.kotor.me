{{--
    Month grid for reservation date (guest + agency panel). Expects $minDate, $maxDate (Y-m-d), optional $selected_date.
--}}
@php
    $minDateStr = $minDate ?? now()->toDateString();
    $maxDateStr = $maxDate ?? now()->addDays(90)->toDateString();
    $locale = app()->getLocale();
    $dateLabel = \App\Support\UiText::t('reservation', 'date');

    $minC = \Illuminate\Support\Carbon::parse($minDateStr)->startOfDay();
    $maxC = \Illuminate\Support\Carbon::parse($maxDateStr)->startOfDay();
    $selectedC = ! empty($selected_date) ? \Illuminate\Support\Carbon::parse($selected_date)->startOfDay() : null;

    $calendarMonthRaw = request()->query('calendar_month');
    try {
        $displayMonth = $calendarMonthRaw
            ? \Illuminate\Support\Carbon::parse($calendarMonthRaw)->startOfMonth()
            : (($selectedC ?? null) ? $selectedC->copy()->startOfMonth() : now()->startOfMonth());
    } catch (\Throwable) {
        $displayMonth = now()->startOfMonth();
    }

    $minMonth = $minC->copy()->startOfMonth();
    $maxMonth = $maxC->copy()->startOfMonth();
    if ($displayMonth->lt($minMonth)) {
        $displayMonth = $minMonth->copy();
    }
    if ($displayMonth->gt($maxMonth)) {
        $displayMonth = $maxMonth->copy();
    }

    $prevMonth = $displayMonth->copy()->subMonth();
    $nextMonth = $displayMonth->copy()->addMonth();
    $canPrevMonth = $prevMonth->gte($minMonth);
    $canNextMonth = $nextMonth->lte($maxMonth);

    $weekdayLabels = $locale === 'cg'
        ? ['Po', 'Ut', 'Sr', 'Če', 'Pe', 'Su', 'Ne']
        : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    $daysInMonth = $displayMonth->daysInMonth;
    $leadingBlankDays = (int) $displayMonth->copy()->startOfMonth()->isoWeekday() - 1;

    try {
        $monthTitle = $displayMonth->copy()->locale($locale === 'cg' ? 'sr_Latn_ME' : 'en')->translatedFormat('F Y');
    } catch (\Throwable) {
        $monthTitle = $displayMonth->format('F Y');
    }
@endphp

<div>
    <x-input-label for="reservation_date" :value="$dateLabel" />
    <input type="hidden" name="reservation_date" id="reservation_date" value="{{ $selected_date ?? '' }}">

    <div class="mt-2 rounded-md border border-red-200 bg-white p-3 shadow-sm">
        <div class="mb-3 flex items-center justify-between gap-2">
            @if ($canPrevMonth)
                <button
                    type="submit"
                    name="calendar_month"
                    value="{{ $prevMonth->format('Y-m-01') }}"
                    class="rounded px-2 py-1 text-sm font-medium text-red-800 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600"
                >
                    ←
                </button>
            @else
                <span class="w-8 shrink-0" aria-hidden="true"></span>
            @endif
            <span class="min-w-0 flex-1 truncate text-center text-sm font-semibold text-gray-900">{{ $monthTitle }}</span>
            @if ($canNextMonth)
                <button
                    type="submit"
                    name="calendar_month"
                    value="{{ $nextMonth->format('Y-m-01') }}"
                    class="rounded px-2 py-1 text-sm font-medium text-red-800 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600"
                >
                    →
                </button>
            @else
                <span class="w-8 shrink-0" aria-hidden="true"></span>
            @endif
        </div>
        <div
            class="mb-1 gap-0.5 text-center text-[0.65rem] font-medium uppercase tracking-wide text-gray-500 sm:text-xs"
            style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));"
        >
            @foreach ($weekdayLabels as $wd)
                <div class="py-1">{{ $wd }}</div>
            @endforeach
        </div>
        <div
            class="gap-1"
            style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));"
            role="grid"
            aria-label="{{ $dateLabel }}"
        >
            @for ($i = 0; $i < $leadingBlankDays; $i++)
                <span class="min-h-[2.25rem]" aria-hidden="true"></span>
            @endfor
            @for ($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $cell = $displayMonth->copy()->day($day);
                    $inRange = ! $cell->lt($minC) && ! $cell->gt($maxC);
                    $isSelected = $selectedC && $selectedC->isSameDay($cell);
                @endphp
                @if ($inRange)
                    <button
                        type="button"
                        data-reservation-date="{{ $cell->toDateString() }}"
                        class="min-h-[2.25rem] w-full max-w-full justify-self-stretch rounded border text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-1 {{ $isSelected ? 'border-red-950 bg-red-700 text-white ring-2 ring-red-800 ring-offset-1' : 'border-red-400 text-gray-900 hover:border-red-700 hover:bg-red-50' }}"
                    >
                        {{ $day }}
                    </button>
                @else
                    <span
                        class="flex min-h-[2.25rem] cursor-default select-none items-center justify-center rounded border border-gray-200 bg-gray-50 text-sm font-normal text-gray-500"
                    >{{ $day }}</span>
                @endif
            @endfor
        </div>
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('reservation_date')" />
</div>
