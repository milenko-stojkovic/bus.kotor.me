<x-guest-layout>
    @php
        $isCg = app()->getLocale() === 'cg';
        $t = fn (string $cg, string $en) => $isCg ? $cg : $en;

        $buttons = [
            ['id' => 'success', 'label' => 'Success', 'bg' => '#16a34a'],
            ['id' => 'deposit_missing', 'label' => 'Deposit missing (58)', 'bg' => '#d97706'],
            ['id' => 'already_fiscalized', 'label' => 'Already fiscalized (78)', 'bg' => '#0ea5e9'],
            ['id' => 'validation_error', 'label' => 'Validation error (11)', 'bg' => '#dc2626'],
            ['id' => 'provider_down', 'label' => 'Provider down (500)', 'bg' => '#7c3aed'],
            ['id' => 'tax_server_error', 'label' => 'Tax server error (900–920)', 'bg' => '#7c3aed'],
            ['id' => 'temporary_service_down', 'label' => 'Temporary service down (999)', 'bg' => '#7c3aed'],
            ['id' => 'timeout', 'label' => 'Timeout', 'bg' => '#475569'],
            ['id' => 'malformed_response', 'label' => 'Malformed response', 'bg' => '#475569'],
        ];
    @endphp

    <div class="space-y-4">
        <div class="space-y-1">
            <h1 class="text-lg font-semibold">{{ $t('Fake fiskal scenariji (lokalno)', 'Fake fiscal scenarios (local)') }}</h1>
            <p class="text-sm text-gray-600">{{ $t('Ovo utiče samo kada je FISCALIZATION_DRIVER=fake.', 'This affects only when FISCALIZATION_DRIVER=fake.') }}</p>
        </div>

        @if (session('message'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('message') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="rounded-md bg-gray-50 p-3 text-sm text-gray-800 space-y-1">
            <div><strong>env</strong>: <code>{{ $env_scenario !== '' ? $env_scenario : '—' }}</code></div>
            <div><strong>session</strong>: <code>{{ $session_scenario ?? '—' }}</code></div>
            <div><strong>tx</strong>: <code>{{ !empty($merchant_transaction_id ?? null) ? $merchant_transaction_id : '—' }}</code></div>
            <div><strong>reservation</strong>:
                <code>
                    @if (!empty($reservation ?? null))
                        #{{ $reservation->id }} / fiscal_jir={{ $reservation->fiscal_jir ? 'YES' : 'NO' }}
                    @else
                        —
                    @endif
                </code>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-2">
            @foreach ($buttons as $b)
                <form method="POST" action="{{ route('payment.fake-fiscal.apply', [], false) }}">
                    @csrf
                    <input type="hidden" name="scenario" value="{{ $b['id'] }}">
                    <input type="hidden" name="merchant_transaction_id" value="{{ $merchant_transaction_id ?? '' }}">
                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center px-4 py-2 rounded-md text-white text-xs font-semibold uppercase tracking-widest hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-offset-2"
                        style="background: {{ $b['bg'] }}; color: #fff;"
                    >
                        {{ $b['label'] }}
                    </button>
                </form>
            @endforeach

            <form method="POST" action="{{ route('payment.fake-fiscal.clear', [], false) }}">
                @csrf
                <input type="hidden" name="merchant_transaction_id" value="{{ $merchant_transaction_id ?? '' }}">
                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 rounded-md text-white text-xs font-semibold uppercase tracking-widest bg-gray-800 hover:bg-gray-700">
                    {{ $t('Resetuj (clear)', 'Reset (clear)') }}
                </button>
            </form>
        </div>

        <p class="text-xs text-gray-500">
            {{ $t('Napomena: ako koristiš QUEUE_CONNECTION=sync, ovaj izbor važi odmah. Ako koristiš queue worker, session izbor se ne prenosi na worker.', 'Note: with QUEUE_CONNECTION=sync this applies immediately. With a queue worker, the session selection will not reach the worker.') }}
        </p>
    </div>
</x-guest-layout>

