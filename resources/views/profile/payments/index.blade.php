<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ app()->getLocale() === 'cg' ? 'Istorija plaćanja' : 'Payment history' }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @include('profile.partials.panel-tabs')

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-sm text-gray-700 space-y-2">
                    <p>
                        {{ app()->getLocale() === 'cg'
                            ? 'Ova sekcija je trenutno minimalan prikaz. Za sada, pregled plaćanja možete posmatrati kroz listu rezervacija.'
                            : 'This section is currently a minimal placeholder. For now, you can use the reservations list as a payment overview.' }}
                    </p>
                    <a href="{{ route('profile.reservations') }}" class="underline text-blue-700 hover:text-blue-900">
                        {{ app()->getLocale() === 'cg' ? 'Otvori rezervacije' : 'Open reservations' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

