<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.password-field-overlay-styles')
    </head>
    <body class="font-sans antialiased">
        <div class="relative isolate flex min-h-screen flex-col bg-red-50">
            @if (request()->routeIs('panel.*'))
                <div
                    aria-hidden="true"
                    class="pointer-events-none fixed inset-0 z-0 bg-red-50"
                    style="background-image: url('{{ asset('images/background.svg') }}'); background-repeat: no-repeat; background-position: center center; background-size: 50% auto;"
                ></div>
            @endif

            <div class="relative z-10 flex min-h-screen flex-1 flex-col">
                @include('layouts.navigation')

                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 w-full">
                    {{ $slot }}
                </main>

                @include('partials.site-footer')
            </div>
        </div>
    </body>
</html>
