<x-guest-layout>
    @php
        $ui = fn (string $key) => \App\Support\UiText::t('landing', $key);
    @endphp

    <div class="space-y-4">
        <div class="space-y-1">
            <h1 class="text-lg font-semibold">
                {{ $ui('title') }}
            </h1>
            <p class="text-sm text-gray-600">
                {{ $ui('subtitle') }}
            </p>
        </div>

        <div class="space-y-3">
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="font-semibold">{{ $ui('guest_title') }}</div>
                <div class="mt-1 text-sm text-gray-600">
                    {{ $ui('guest_description') }}
                </div>
                <a
                    href="{{ route('guest.reserve', [], false) }}"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition"
                >
                    {{ $ui('guest_cta') }}
                </a>
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <div class="font-semibold">{{ $ui('agency_title') }}</div>
                <div class="mt-1 text-sm text-gray-600">
                    {{ $ui('agency_description') }}
                </div>
                <a
                    href="{{ route('login', [], false) }}"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition"
                >
                    {{ $ui('agency_cta') }}
                </a>
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <div class="font-semibold">{{ $ui('students_title', 'Učenici/humanitarci') }}</div>
                <div class="mt-1 text-sm text-gray-600">
                    {{ $ui('students_description', 'Za obrazovne ustanove i humanitarne organizacije') }}
                </div>
                <a
                    href="{{ route('free-request.create', [], false) }}"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition"
                >
                    {{ $ui('students_cta', 'Forma za besplatne rezervacije') }}
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>

