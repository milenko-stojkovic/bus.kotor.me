<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin – Rezervacije') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('admin.reservations.index') }}" method="get" class="mb-6 flex flex-wrap gap-2 items-end">
                        <div class="flex-1 min-w-[200px]">
                            <label for="q" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Pretraga (email, tablica, ime, ID transakcije)') }}</label>
                            <input type="text" name="q" id="q" value="{{ old('q', $search) }}"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="{{ __('Pretraži...') }}">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('Pretraži') }}
                        </button>
                        @if($search !== '')
                            <a href="{{ route('admin.reservations.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none transition">
                                {{ __('Prikaži naredna 3h') }}
                            </a>
                        @endif
                    </form>

                    @if($search === '')
                        <p class="text-sm text-gray-600 mb-4">{{ __('Prikaz: rezervacije čiji drop-off termin je u naredna 3 sata. Za sve rezervacije koristite pretragu.') }}</p>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Datum') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Drop-off') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Pick-up') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Ime') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Email') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Tablica') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Tx ID') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($reservations as $r)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">{{ $r->reservation_date?->format('d.m.Y') }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $r->dropOffTimeSlot?->time_slot }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $r->pickUpTimeSlot?->time_slot }}</td>
                                        <td class="px-4 py-2 text-gray-900">{{ $r->user_name }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ $r->email }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-gray-900">{{ $r->license_plate }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded text-xs {{ $r->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ $r->status }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500 font-mono text-xs">{{ $r->merchant_transaction_id }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                            {{ $search !== '' ? __('Nema rezultata pretrage.') : __('Nema rezervacija u naredna 3 sata.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($reservations->hasPages())
                        <div class="mt-4">
                            {{ $reservations->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
