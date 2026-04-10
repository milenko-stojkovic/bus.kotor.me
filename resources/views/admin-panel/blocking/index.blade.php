@php
    $fmtDate = fn (string $d) => \Carbon\Carbon::parse($d)->format('d.m.Y.');
@endphp

<x-admin-panel-layout page-title="Blokiranje" nav-active="blocking">
    <div class="space-y-10">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Blokirani dani i termini</h2>

            @if (empty($blockedDays))
                <p class="text-sm text-gray-600">Nema blokiranih termina.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($blockedDays as $day)
                        <li class="bg-white shadow rounded-lg p-4 flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900">
                                    {{ $fmtDate($day['date']) }}
                                    @if ($day['is_full_day'])
                                        <span class="text-gray-600">— blokiran</span>
                                    @endif
                                </div>
                                @if (! $day['is_full_day'])
                                    <div class="mt-1 text-sm text-gray-700">
                                        {{ implode(', ', $day['ranges']) }}
                                    </div>
                                @endif
                            </div>
                            <a href="{{ route('panel_admin.blocking.day', ['date' => $day['date']], false) }}"
                               class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                Deblokiraj
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Blokiraj</h2>

            <form method="GET" action="{{ route('panel_admin.blocking', [], false) }}" class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <x-input-label for="date" value="Datum" />
                    <x-text-input id="date" type="date" name="date" class="mt-1" :value="$selectedDate" />
                </div>
                <x-secondary-button type="submit">Prikaži</x-secondary-button>
            </form>

            <form method="POST" action="{{ route('panel_admin.blocking.apply', [], false) }}" class="bg-white shadow rounded-lg p-5 space-y-4">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate }}">

                <div class="flex items-center gap-2">
                    <input id="block_whole_day" type="checkbox" name="block_whole_day" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <label for="block_whole_day" class="text-sm text-gray-700">Blokiraj ceo dan</label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach ($slots as $slot)
                        @php
                            $daily = $dailyBySlotId->get($slot->id);
                            $reserved = (int) ($daily?->reserved ?? 0);
                            $pending = (int) ($daily?->pending ?? 0);
                            $blocked = (bool) ($daily?->is_blocked ?? false);
                            $hasProblem = $reserved > 0 || $pending > 0;
                        @endphp
                        <label class="flex items-center gap-2 p-2 rounded border {{ $blocked ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200' }}">
                            <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ $blocked ? 'checked' : '' }}>
                            <span class="text-sm text-gray-900">{{ $slot->time_slot }}</span>
                            <span class="text-xs text-gray-500 ms-auto">
                                r:{{ $reserved }} p:{{ $pending }}
                                @if ($hasProblem)
                                    <span class="text-amber-700 font-medium">— zahvaćeno</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <x-primary-button type="submit">Primeni</x-primary-button>
                </div>
            </form>

            <div class="mt-8">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Rezervacije u blok zoni</h3>
                @if ($worklist->isEmpty())
                    <p class="text-sm text-gray-600">Nema otvorenih stavki.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($worklist as $row)
                            @php
                                $snap = (array) ($row->snapshot_json ?? []);
                            @endphp
                            <li class="bg-white shadow rounded-lg p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900">
                                            {{ $snap['user_name'] ?? '—' }}
                                            <span class="text-gray-500 font-normal">({{ $snap['email'] ?? '—' }})</span>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-700">
                                            Datum: {{ $fmtDate($row->old_date->toDateString()) }}
                                        </div>
                                        <div class="mt-1 text-sm text-gray-700">
                                            Drop-off: <span class="{{ $row->affected_drop_off ? 'font-semibold text-amber-800' : '' }}">#{{ $row->old_drop_off }}</span>,
                                            Pick-up: <span class="{{ $row->affected_pick_up ? 'font-semibold text-amber-800' : '' }}">#{{ $row->old_pick_up }}</span>
                                        </div>
                                        <div class="mt-2 text-xs text-gray-500">
                                            Status: <span class="font-medium">{{ $row->status }}</span>
                                            · MTID: {{ $row->merchant_transaction_id }}
                                        </div>
                                        @if ($row->status === \App\Models\BlockZoneWorklist::STATUS_PENDING_PAYMENT)
                                            <div class="mt-2 text-sm text-amber-800">
                                                Pending payment. Ručno refresh-ujte stranicu da proverite promenu stanja.
                                            </div>
                                        @endif
                                    </div>
                                    <div class="shrink-0">
                                        @if ($row->status === \App\Models\BlockZoneWorklist::STATUS_READY_TO_ADJUST)
                                            <a href="{{ route('panel_admin.blocking.worklist.adjust', $row, false) }}"
                                               class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                                Prilagodi rezervaciju
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
</x-admin-panel-layout>

