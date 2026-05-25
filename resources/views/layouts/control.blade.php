<!DOCTYPE html>
<html lang="sr-Latn-ME">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle ?? 'Kontrola' }} — {{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.password-field-overlay-styles')
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="relative isolate flex min-h-screen flex-col bg-red-50">
            <div
                aria-hidden="true"
                class="pointer-events-none fixed inset-0 z-0 bg-red-50"
                style="background-image: url('{{ asset('images/background.svg') }}'); background-repeat: no-repeat; background-position: center center; background-size: 50% auto;"
            ></div>

            <div class="relative z-10 flex min-h-screen flex-1 flex-col">
                <div class="mx-auto w-full max-w-6xl flex-1 px-4 py-4 sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>

                @include('partials.site-footer')
            </div>
        </div>
    </body>
</html>
