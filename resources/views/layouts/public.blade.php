<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ $metaDescription ?? 'Find verified homes in Akure, inspect before you commit, and pay through a clear, safer process with VerifyHomes.' }}">

        <title>{{ $title ?? 'VerifyHomes - Verified Homes in Akure' }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="min-h-screen">
            <header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" class="flex shrink-0 items-center" aria-label="VerifyHomes home">
                        <x-application-logo class="h-12 w-auto sm:h-14" />
                    </a>

                    <nav class="hidden items-center gap-6 text-base font-medium text-slate-600 lg:flex" aria-label="Primary navigation">
                        <a href="{{ route('properties.index') }}" class="transition-colors duration-200 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2">Browse homes</a>
                        <a href="{{ route('home') }}#how-it-works" class="transition-colors duration-200 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2">How it works</a>
                        <a href="{{ route('home') }}#for-landlords" class="transition-colors duration-200 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2">For landlords</a>
                        <a href="{{ route('support') }}" class="transition-colors duration-200 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2">Support</a>
                    </nav>

                    <div class="hidden items-center gap-3 sm:flex">
                        @auth
                            <a href="{{ route('dashboard') }}" class="vh-button vh-button-secondary">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="text-base font-medium text-slate-700 transition-colors duration-200 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2">Login</a>
                            <a href="{{ route('register.tenant') }}" class="vh-button vh-button-primary">Get started</a>
                        @endauth
                    </div>

                    <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border border-slate-300 text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2 sm:hidden" x-on:click="open = !open" x-on:keydown.escape.window="open = false" :aria-expanded="open.toString()" aria-controls="public-mobile-menu" aria-label="Toggle navigation menu">
                        <svg x-show="!open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        <svg x-show="open" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <nav id="public-mobile-menu" x-show="open" x-cloak x-transition class="border-t border-slate-200 bg-white px-4 py-4 sm:hidden" aria-label="Mobile navigation">
                    <div class="mx-auto flex max-w-7xl flex-col gap-1">
                        <a x-on:click="open = false" href="{{ route('properties.index') }}" class="rounded-md px-3 py-3 text-base font-medium text-slate-700 transition-colors duration-200 hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-inset">Browse homes</a>
                        <a x-on:click="open = false" href="{{ route('home') }}#how-it-works" class="rounded-md px-3 py-3 text-base font-medium text-slate-700 transition-colors duration-200 hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-inset">How it works</a>
                        <a x-on:click="open = false" href="{{ route('home') }}#for-landlords" class="rounded-md px-3 py-3 text-base font-medium text-slate-700 transition-colors duration-200 hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-inset">For landlords</a>
                        <a x-on:click="open = false" href="{{ route('support') }}" class="rounded-md px-3 py-3 text-base font-medium text-slate-700 transition-colors duration-200 hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-inset">Support</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="vh-button vh-button-secondary mt-2">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-md px-3 py-3 text-base font-medium text-slate-700 transition-colors duration-200 hover:bg-teal-50 hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-inset">Login</a>
                            <a href="{{ route('register.tenant') }}" class="vh-button vh-button-primary mt-2">Get started</a>
                        @endauth
                    </div>
                </nav>
            </header>

            <main>
                {{ $slot ?? '' }}
                @yield('content')
            </main>

            <footer class="border-t border-slate-200 bg-slate-950 text-slate-300">
                <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[1.4fr_repeat(2,1fr)] lg:px-8">
                    <div>
                        <x-application-logo class="h-12 w-auto brightness-0 invert" />
                        <p class="mt-4 max-w-sm text-sm leading-6 text-slate-400">Verified homes in Akure, with inspection before commitment and a clearer payment process.</p>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-white">Explore</h2>
                        <div class="mt-4 flex flex-col gap-3 text-sm"><a href="{{ route('properties.index') }}" class="hover:text-white">Browse homes</a><a href="{{ route('register.landlord') }}" class="hover:text-white">List property</a><a href="{{ route('home') }}#how-it-works" class="hover:text-white">How it works</a></div>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-white">Account and help</h2>
                        <div class="mt-4 flex flex-col gap-3 text-sm"><a href="{{ route('support') }}" class="hover:text-white">Support</a><a href="{{ route('terms') }}" class="hover:text-white">Terms</a><a href="{{ route('privacy') }}" class="hover:text-white">Privacy</a><a href="{{ route('login') }}" class="hover:text-white">Login</a><a href="{{ route('register.tenant') }}" class="hover:text-white">Register</a></div>
                    </div>
                </div>
                <div class="border-t border-slate-800"><div class="mx-auto max-w-7xl px-4 py-5 text-xs text-slate-500 sm:px-6 lg:px-8">&copy; {{ now()->year }} VerifyHomes. Built for clearer property decisions.</div></div>
            </footer>
        </div>

        @livewireScripts
    </body>
</html>
