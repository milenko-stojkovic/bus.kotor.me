<x-guest-layout>
<div class="space-y-4">
    <p class="text-sm text-gray-600">Simulate bank payment (test). Transaction: <code class="bg-gray-100 px-1">{{ $merchant_transaction_id }}</code></p>
    <p class="text-xs text-amber-600">Frontend nikad ne poziva bank callback (/api/payments/callback). Ovo koristi poseban test endpoint.</p>
    <div class="flex flex-wrap gap-3">
        @php
            $scenarios = [
                ['id' => 'success', 'label' => 'Success', 'cls' => 'bg-green-600 hover:bg-green-500 focus:bg-green-700 focus:ring-green-500'],
                ['id' => 'cancel', 'label' => 'Cancel (user_cancelled)', 'cls' => 'bg-slate-600 hover:bg-slate-500 focus:bg-slate-700 focus:ring-slate-500'],
                ['id' => 'expired', 'label' => 'Expired (transaction_expired)', 'cls' => 'bg-amber-600 hover:bg-amber-500 focus:bg-amber-700 focus:ring-amber-500'],
                ['id' => 'declined', 'label' => 'Declined (authorization_declined)', 'cls' => 'bg-red-600 hover:bg-red-500 focus:bg-red-700 focus:ring-red-500'],
                ['id' => 'insufficient_funds', 'label' => 'Insufficient funds', 'cls' => 'bg-red-600 hover:bg-red-500 focus:bg-red-700 focus:ring-red-500'],
                ['id' => '3ds_failed', 'label' => '3DS failed', 'cls' => 'bg-red-600 hover:bg-red-500 focus:bg-red-700 focus:ring-red-500'],
                ['id' => 'system_error', 'label' => 'System error', 'cls' => 'bg-purple-600 hover:bg-purple-500 focus:bg-purple-700 focus:ring-purple-500'],
            ];
        @endphp

        @foreach ($scenarios as $s)
            <form method="GET" action="{{ route('fake-bank.complete') }}" class="inline">
                <input type="hidden" name="tx" value="{{ $merchant_transaction_id }}">
                <input type="hidden" name="scenario" value="{{ $s['id'] }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150 {{ $s['cls'] }}">
                    {{ $s['label'] }}
                </button>
            </form>
        @endforeach
    </div>
    <p class="text-xs text-gray-500">Nakon klika redirect na <a href="{{ route('payment.return', ['merchant_transaction_id' => $merchant_transaction_id]) }}" class="underline">/payment/return</a> – status se uvek čita iz baze.</p>
</div>
</x-guest-layout>
