<x-guest-layout :landing-background="true">
    @php
        $locale = app()->getLocale();
        $ui = fn (string $key, ?string $fallback = null) => \App\Support\UiText::t('auth', $key, $fallback);
    @endphp

    <div class="mb-4 text-sm text-gray-600">
        {{ $ui(
            'forgot_password_prompt',
            $locale === 'en'
                ? 'Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.'
                : 'Zaboravili ste lozinku? Nema problema. Unesite svoju email adresu i poslaćemo vam link za reset lozinke pomoću kojeg možete odabrati novu.',
        ) }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="$ui('email', 'Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ $ui(
                    'forgot_password_send_link',
                    $locale === 'en'
                        ? 'Email Password Reset Link'
                        : 'Pošalji link za reset lozinke',
                ) }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
