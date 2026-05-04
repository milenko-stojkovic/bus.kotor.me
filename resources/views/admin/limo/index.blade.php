@php
    $fmtMoney = fn ($v) => number_format((float) $v, 2, ',', '').' EUR';
    $fmtWhen = fn ($dt) => $dt->timezone('Europe/Podgorica')->format('d.m.Y H:i');
    $labelSource = static fn (?string $s) => match ($s) {
        'qr' => 'QR',
        'plate' => 'Tablica',
        default => $s ?? '—',
    };
    $labelStatus = static fn (?string $s) => match ($s) {
        'pending_fiscal' => 'U obradi',
        'fiscalized' => 'Fiskalizovano',
        'fiscal_failed' => 'Greška fiskalizacije',
        default => $s ?? '—',
    };
@endphp

@component('layouts.admin-panel', ['pageTitle' => $pageTitle ?? 'Limo pickup', 'navActive' => 'limo'])
    <div class="space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Limo pickup događaji</h1>
            <p class="text-sm text-gray-600 mt-1">Samo pregled (bez akcija). Filtar po datumu važi za vrijeme događaja (<code class="text-xs bg-gray-100 px-1 rounded">occurred_at</code>), zona Europe/Podgorica.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 space-y-1">
                @foreach ($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        <form method="get" action="{{ route('admin.limo.index', [], false) }}" class="bg-white shadow rounded-lg p-5 border border-gray-100 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <x-input-label for="date_from" value="Datum od" />
                    <input type="date" id="date_from" name="date_from"
                           value="{{ old('date_from', $dateFrom) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                </div>
                <div>
                    <x-input-label for="date_to" value="Datum do" />
                    <input type="date" id="date_to" name="date_to"
                           value="{{ old('date_to', $dateTo) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                </div>
                <div class="flex gap-2 justify-start md:justify-end">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Filtriraj
                    </button>
                </div>
            </div>
        </form>

        <div class="bg-white shadow rounded-lg border border-gray-100 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Datum i vrijeme</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Agencija</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Tablica</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Iznos</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Izvor</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">JIR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($events as $event)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $fmtWhen($event->occurred_at) }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $event->agency_name_snapshot }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $event->license_plate_snapshot }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $fmtMoney($event->amount_snapshot) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $labelSource($event->source) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-900">{{ $labelStatus($event->status) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $event->fiscal_jir ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">Nema događaja u izabranom periodu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endcomponent
