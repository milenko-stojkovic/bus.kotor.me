<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Moje rezervacije') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="get" action="{{ route('profile.reservations') }}" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Datum') }}</label>
                            <input id="date" name="date" type="date" value="{{ $filters['date'] }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                            <input id="status" name="status" value="{{ $filters['status'] }}" placeholder="paid" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">{{ __('Filtriraj') }}</button>
                            <a href="{{ route('profile.reservations') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">{{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Datum') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Drop-off') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Pick-up') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Vozilo') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Fiskalizacija') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Račun') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($reservations as $r)
                                    <tr>
                                        <td class="px-4 py-2">{{ $r->reservation_date?->format('d.m.Y') }}</td>
                                        <td class="px-4 py-2">{{ $r->dropOffTimeSlot?->time_slot }}</td>
                                        <td class="px-4 py-2">{{ $r->pickUpTimeSlot?->time_slot }}</td>
                                        <td class="px-4 py-2">{{ $r->license_plate }}</td>
                                        <td class="px-4 py-2">{{ $r->status }}</td>
                                        <td class="px-4 py-2">{{ $r->fiscal_jir ? __('Fiskalizovano') : __('Nefiskalizovano / čekanje') }}</td>
                                        <td class="px-4 py-2">
                                            @if($r->invoice_pdf_path)
                                                <a href="{{ route('profile.reservations.invoice', $r->id) }}" class="text-indigo-600 hover:text-indigo-800">{{ __('PDF') }}</a>
                                            @else
                                                <span class="text-gray-500">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('Nema rezervacija.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($reservations->hasPages())
                        <div class="mt-4">{{ $reservations->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
