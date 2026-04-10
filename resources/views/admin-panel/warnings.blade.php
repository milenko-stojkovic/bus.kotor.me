@php
    $statusLabel = fn (string $s) => match ($s) {
        \App\Models\AdminAlert::STATUS_UNREAD => 'Nepročitano',
        \App\Models\AdminAlert::STATUS_IN_PROGRESS => 'U obradi',
        \App\Models\AdminAlert::STATUS_DONE => 'Završeno',
        default => $s,
    };
@endphp

@push('head')
    <meta http-equiv="refresh" content="300">
@endpush

<x-admin-panel-layout page-title="Upozorenja / Informacije" nav-active="dashboard">
    <div class="space-y-10">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Upozorenja</h2>
            @if ($alerts->isEmpty())
                <p class="text-gray-600 text-sm">Nema aktivnih upozorenja.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($alerts as $alert)
                        <li class="bg-white shadow rounded-lg p-5 border border-gray-100">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-medium text-gray-900">{{ $alert->title }}</h3>
                                    <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap">{{ $alert->message }}</p>
                                    <dl class="mt-3 text-xs text-gray-500 space-y-1">
                                        <div><span class="font-medium text-gray-600">Status:</span> {{ $statusLabel($alert->status) }}</div>
                                        <div><span class="font-medium text-gray-600">Kreirano:</span> {{ $alert->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                                    </dl>
                                </div>
                                <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                                    <button type="button"
                                        class="inline-flex justify-center items-center px-3 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                                        data-b64="{{ base64_encode($alert->copyDetailsText()) }}"
                                        onclick="(function(btn){var t=atob(btn.dataset.b64);navigator.clipboard.writeText(t).then(function(){btn.replaceWith(Object.assign(document.createElement('span'),{className:'text-xs text-green-700',textContent:'Kopirano'}));}).catch(function(){alert('Kopiranje nije uspelo');});})(this)">
                                        Copy details
                                    </button>
                                    @if ($alert->status === \App\Models\AdminAlert::STATUS_UNREAD)
                                        <form method="POST" action="{{ route('panel_admin.alerts.transition', $alert, false) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="in_progress">
                                            <x-secondary-button type="submit">U obradi</x-secondary-button>
                                        </form>
                                    @elseif ($alert->status === \App\Models\AdminAlert::STATUS_IN_PROGRESS)
                                        <form method="POST" action="{{ route('panel_admin.alerts.transition', $alert, false) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="done">
                                            <x-secondary-button type="submit">Završen</x-secondary-button>
                                        </form>
                                    @elseif ($alert->status === \App\Models\AdminAlert::STATUS_DONE)
                                        <form method="POST" action="{{ route('panel_admin.alerts.transition', $alert, false) }}"
                                            onsubmit="return confirm('Ukloniti ovaj alert sa liste?');">
                                            @csrf
                                            <input type="hidden" name="action" value="remove">
                                            <x-danger-button type="submit">Ukloni</x-danger-button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Nedostupni dani i termini</h2>
            <div class="bg-white shadow rounded-lg p-5 border border-dashed border-amber-200">
                <p class="text-sm text-gray-600">
                    U ovom koraku nije implementirano. Ovde će biti prikaz slotova koji nisu dostupni za novu prodaju
                    (blokirani i oni bez slobodnog kapaciteta po availability logici).
                </p>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Blokirani dani i termini</h2>
            <div class="bg-white shadow rounded-lg p-5 border border-dashed border-amber-200">
                <p class="text-sm text-gray-600">
                    U ovom koraku nije implementirano. Blokiranje sprečava novu prodaju bez menjanja capacity/reserved/pending.
                </p>
            </div>
        </section>
    </div>
</x-admin-panel-layout>
