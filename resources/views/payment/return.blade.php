@php
    $backUrl = auth()->check()
        ? ($result['redirect_auth'] ?? route('panel.reservations', [], false))
        : ($result['redirect_guest'] ?? route('guest.reserve', [], false));
@endphp

@auth
    <x-app-layout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                @include('payment.partials.return-pending-body', [
                    'merchantTransactionId' => $merchant_transaction_id,
                    'backUrl' => $backUrl,
                ])
            </div>
        </div>
    </x-app-layout>
@else
    <x-guest-layout>
        @include('payment.partials.return-pending-body', [
            'merchantTransactionId' => $merchant_transaction_id,
            'backUrl' => $backUrl,
        ])
    </x-guest-layout>
@endauth
