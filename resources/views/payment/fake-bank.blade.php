<x-guest-layout>
<div class="space-y-4">
    <p class="text-sm text-gray-600">Simulate bank payment (test). Transaction: <code class="bg-gray-100 px-1">{{ $merchant_transaction_id }}</code></p>
    <p class="text-xs text-amber-600">Frontend nikad ne poziva bank callback (/api/payments/callback). Ovo koristi poseban test endpoint.</p>
    <div class="flex gap-4">
        <form method="POST" action="{{ route('payment.fake-bank.complete') }}" class="inline">
            @csrf
            <input type="hidden" name="merchant_transaction_id" value="{{ $merchant_transaction_id }}">
            <input type="hidden" name="status" value="success">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Success
            </button>
        </form>
        <form method="POST" action="{{ route('payment.fake-bank.complete') }}" class="inline">
            @csrf
            <input type="hidden" name="merchant_transaction_id" value="{{ $merchant_transaction_id }}">
            <input type="hidden" name="status" value="failed">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Fail
            </button>
        </form>
    </div>
    <p class="text-xs text-gray-500">After clicking, check status at: <a href="{{ route('reservation.status', ['merchant_transaction_id' => $merchant_transaction_id]) }}" class="underline">/reservation-status/{{ $merchant_transaction_id }}</a></p>
</div>
</x-guest-layout>
