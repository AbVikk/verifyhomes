<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'VerifyHomes') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="relative min-h-screen min-h-[100dvh] overflow-hidden bg-slate-950 bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('assets/images/login_back.png') }}');">
            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/45 via-slate-950/55 to-slate-950/65"></div>

            <div class="relative z-10 flex min-h-screen min-h-[100dvh] w-full flex-col items-center px-4 py-8 sm:px-6 sm:py-12">
                <div class="my-auto flex w-full max-w-md flex-col items-center">
                    <a href="/" class="mb-5 inline-flex items-center justify-center rounded-xl border border-white/70 bg-white/95 px-4 py-2 shadow-[0_14px_34px_rgba(2,6,23,0.24)] transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-teal-300 focus:ring-offset-2 focus:ring-offset-slate-950">
                        <x-application-logo class="h-16 w-auto sm:h-20" />
                    </a>

                    <div class="w-full overflow-hidden rounded-xl border border-slate-200 bg-white px-6 py-6 shadow-[0_22px_50px_rgba(2,6,23,0.32)] sm:px-8 sm:py-7">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
