<x-guest-layout>
<div class="space-y-4">
    @if ($result['status'] === 'success')
        <p class="text-green-700 font-medium">Rezervacija je uspešno kreirana.</p>
        <p class="text-sm text-gray-600">Hvala vam na plaćanju. Potvrda će vam stići putem emaila.</p>
        <a href="{{ $result['user_type'] === 'auth' ? $result['redirect_auth'] : $result['redirect_guest'] }}"
           class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
            {{ $result['user_type'] === 'auth' ? __('Moje rezervacije') : __('Nova rezervacija') }}
        </a>
    @elseif ($result['status'] === 'pending')
        <p class="text-amber-700 font-medium">Plaćanje se obrađuje.</p>
        <p class="text-sm text-gray-600">Ako ste završili plaćanje, sačekajte ili osvežite stranicu. Status se uvek čita iz baze.</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ url()->current() }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
                Osvežite stranicu
            </a>
            <a href="{{ $result['redirect_guest'] }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none transition">
                Početna
            </a>
        </div>
        {{-- Polling: kada callback stigne, osveži da prikaže success --}}
        <script>
            (function() {
                var txId = @json($merchant_transaction_id);
                var interval = setInterval(function() {
                    fetch('{{ route("payment.result") }}?merchant_transaction_id=' + encodeURIComponent(txId), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function(r) { return r.json(); }).then(function(data) {
                        if (data.status === 'success') { clearInterval(interval); window.location.reload(); }
                    }).catch(function() {});
                }, 3000);
                setTimeout(function() { clearInterval(interval); }, 300000);
            })();
        </script>
    @else
        <p class="text-red-700 font-medium">{{ $result['message'] ?? __('Plaćanje je otkazano ili nije uspelo. Rezervacija nije sačuvana.') }}</p>
        <a href="{{ $result['redirect_guest'] }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
            {{ __('Nova rezervacija') }}
        </a>
    @endif
</div>
</x-guest-layout>
