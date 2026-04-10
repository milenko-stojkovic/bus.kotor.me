<x-admin-panel-layout page-title="Deblokiranje" nav-active="blocking">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">Deblokiranje</h1>
                <p class="text-sm text-gray-600">Datum: {{ \Carbon\Carbon::parse($date)->format('d.m.Y.') }}</p>
            </div>
            <a href="{{ route('panel_admin.blocking', [], false) }}" class="text-sm text-indigo-700 hover:underline">Nazad</a>
        </div>

        <form method="POST" action="{{ route('panel_admin.blocking.unblock.apply', [], false) }}" class="bg-white shadow rounded-lg p-5 space-y-4">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">

            <div class="flex items-center gap-2">
                <input id="unblock_all" type="checkbox" name="unblock_all" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                <label for="unblock_all" class="text-sm text-gray-700">Deblokiraj sve (ceo dan)</label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach ($slots as $slot)
                    @php
                        $daily = $dailyBySlotId->get($slot->id);
                        $blocked = (bool) ($daily?->is_blocked ?? false);
                    @endphp
                    <label class="flex items-center gap-2 p-2 rounded border {{ $blocked ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200 opacity-60' }}">
                        <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ $blocked ? 'checked' : '' }}>
                        <span class="text-sm text-gray-900">{{ $slot->time_slot }}</span>
                    </label>
                @endforeach
            </div>

            <div class="flex justify-end">
                <x-primary-button type="submit">Primeni</x-primary-button>
            </div>
        </form>
    </div>
</x-admin-panel-layout>

