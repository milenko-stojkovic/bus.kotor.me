@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<\App\Models\User> $users */
    $users = $users ?? null;
    $advanceEnabled = (bool) ($advanceEnabled ?? false);
@endphp

<x-admin-panel-layout page-title="Agencije" nav-active="agencies">
    <div class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold text-gray-900">Agencije</h1>
            <p class="text-sm text-gray-600 mt-1">Lista korisnika/agencija i pregled avansa (read-only).</p>
        </header>

        <section class="bg-white shadow rounded-lg p-4 sm:p-6">
            <form method="get" action="{{ route('panel_admin.agencies.index', [], false) }}" class="mb-6 flex flex-wrap gap-2 items-end">
                <div class="flex-1 min-w-[220px]">
                    <label for="agency-search-q" class="block text-sm font-medium text-gray-700 mb-1">Pretraga agencija (ime ili email)</label>
                    <input
                        type="search"
                        name="q"
                        id="agency-search-q"
                        value="{{ old('q', $search ?? '') }}"
                        class="block w-full rounded-md border-red-200 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
                        placeholder="Npr. naziv agencije ili dio email adrese…"
                        autocomplete="off"
                    />
                    <p class="mt-1 text-xs text-gray-500">Heuristička pretraga — dopušteni su djelimični podudarnosti i blage greške u kucanju.</p>
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-800 focus:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Pretraži
                </button>
                @if (($search ?? '') !== '')
                    <a href="{{ route('panel_admin.agencies.index', [], false) }}" class="inline-flex items-center px-4 py-2 border border-red-200 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-red-50 focus:outline-none transition">
                        Obriši filter
                    </a>
                @endif
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-red-100 text-gray-600">
                            <th class="py-2 pr-4">Naziv</th>
                            <th class="py-2 pr-4">Email</th>
                            <th class="py-2 pr-4">Registracija</th>
                            <th class="py-2 pr-4">Avans</th>
                            <th class="py-2 pr-4">Rezervacije</th>
                            <th class="py-2 pr-4">Detalji</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            @php
                                $bal = number_format((float) ($u->advance_balance ?? 0), 2, '.', '');
                            @endphp
                            <tr class="border-b border-red-100">
                                <td class="py-2 pr-4 font-medium">{{ $u->name }}</td>
                                <td class="py-2 pr-4">{{ $u->email }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $u->created_at?->format('d.m.Y.') ?? '—' }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap">
                                    @if ($advanceEnabled)
                                        {{ $bal }} EUR
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ (int) ($u->reservations_count ?? 0) }}</td>
                                <td class="py-2 pr-4">
                                    <a class="text-red-700 underline font-medium" href="{{ route('panel_admin.agencies.show', $u, false) }}">
                                        Detalji
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        @if ($users->isEmpty())
                            <tr>
                                <td class="py-4 text-gray-600" colspan="6">
                                    @if (($search ?? '') !== '')
                                        Nema rezultata pretrage.
                                    @else
                                        Nema agencija.
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </section>
    </div>
</x-admin-panel-layout>

