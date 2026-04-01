<x-guest-layout>
<div class="space-y-4">
    @if ($result['status'] === 'success')
        <p class="text-green-700 font-medium">Rezervacija je uspešno kreirana.</p>
        @if (!empty($result['is_free_reservation'] ?? false))
            <p class="text-sm text-gray-600">{{ \App\Support\UiText::t('checkout', 'free_return_blurb', 'This was a free reservation — no payment or fiscal invoice. A confirmation was sent to your email.') }}</p>
        @else
            <p class="text-sm text-gray-600">Hvala vam na plaćanju. Potvrda će vam stići putem emaila.</p>
        @endif
        @if (empty($result['is_free_reservation'] ?? false) && app()->environment('local') && config('services.fiscalization.driver') === 'fake')
            <div class="rounded-md bg-blue-50 p-3 text-sm text-blue-800">
                <div class="font-medium">Fake fiskal scenariji</div>
                <a href="{{ route('payment.fake-fiscal', ['merchant_transaction_id' => $merchant_transaction_id], false) }}" class="underline">
                    Otvori panel za izbor scenarija
                </a>
            </div>
        @endif
        <a href="{{ $result['user_type'] === 'auth' ? $result['redirect_auth'] : $result['redirect_guest'] }}"
           class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
            {{ $result['user_type'] === 'auth' ? __('Moje rezervacije') : (!empty($result['is_free_reservation'] ?? false) ? \App\Support\UiText::t('checkout', 'free_return_cta_guest', 'Back to booking') : __('Nova rezervacija')) }}
        </a>
    @elseif ($result['status'] === 'late_success')
        <p class="text-amber-700 font-medium">{{ $result['message'] ?? __('Payment was confirmed after the reservation window closed. Please contact support.') }}</p>
        <a href="{{ $result['user_type'] === 'auth' ? $result['redirect_auth'] : $result['redirect_guest'] }}"
           class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
            {{ $result['user_type'] === 'auth' ? __('Moje rezervacije') : __('Nova rezervacija') }}
        </a>
    @elseif ($result['status'] === 'pending')
        <p class="text-amber-700 font-medium">{{ \App\Support\UiText::t('checkout', 'payment_pending', 'Payment pending') }}</p>
        <p class="text-sm text-gray-600">Ako ste završili plaćanje, sačekajte ili osvežite stranicu. Status se uvek čita iz baze.</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ request()->fullUrl() }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
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
        <p class="text-red-700 font-medium">{{ $result['message'] ?? __('Plaćanje nije uspelo. Vaši podaci su sačuvani – pokušajte ponovo.') }}</p>
        @if(!empty($result['error_reason'] ?? null))
            <p class="text-sm text-gray-600 mt-1">{{ __('Razlog (banka)') }}: {{ $result['error_reason'] }}</p>
        @endif
        <a href="{{ $result['redirect_guest'] ?? route('reservations.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
            {{ __('Nova rezervacija') }}
        </a>
    @endif
</div>
</x-guest-layout>
