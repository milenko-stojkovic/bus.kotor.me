@php
    $tabs = [
        ['route' => 'profile.reservations', 'label' => app()->getLocale() === 'cg' ? 'Rezervacije' : 'Reservations'],
        ['route' => 'profile.edit', 'label' => app()->getLocale() === 'cg' ? 'Korisnik' : 'User'],
        ['route' => 'profile.vehicles.index', 'label' => app()->getLocale() === 'cg' ? 'Vozni park' : 'Vehicles'],
        ['route' => 'profile.payments', 'label' => app()->getLocale() === 'cg' ? 'Istorija plaćanja' : 'Payment history'],
    ];
@endphp

<nav class="flex flex-wrap gap-2">
    @foreach ($tabs as $t)
        @php
            $isActive = request()->routeIs($t['route']);
        @endphp
        <a
            href="{{ route($t['route']) }}"
            class="{{ $isActive ? 'bg-gray-800 text-white' : 'bg-white text-gray-800 border border-gray-300 hover:bg-gray-50' }} inline-flex items-center px-3 py-2 rounded-md text-xs font-semibold uppercase tracking-widest"
        >
            {{ $t['label'] }}
        </a>
    @endforeach
</nav>

