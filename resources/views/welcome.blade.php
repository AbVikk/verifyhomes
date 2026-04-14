<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'VerifyHomes') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-50 text-slate-900 antialiased">
        <div class="min-h-screen">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" class="flex items-center text-slate-900">
                        <x-application-logo class="h-16 w-auto sm:h-20" />
                    </a>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-3 text-sm font-medium text-slate-600 sm:gap-4">
                            @auth
                                <a href="{{ route('dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2 text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                                    Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="transition hover:text-slate-900">
                                    Log in
                                </a>

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="rounded-md bg-slate-900 px-4 py-2 text-white transition hover:bg-slate-700">
                                        Get Started
                                    </a>
                                @endif
                            @endauth
                        </nav>
                    @endif
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:items-center">
                    <section class="space-y-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-700">
                            Akure Launch Focus
                        </p>
                        <h1 class="max-w-3xl text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">
                            Verified landlord-to-tenant rentals built for safer home search in Akure, Ondo State.
                        </h1>
                        <p class="max-w-2xl text-lg leading-8 text-slate-600">
                            VerifyHomes helps renters inspect first, pay with more confidence, and reduce the agent stress that often comes with finding a place to live.
                        </p>

                        <div class="flex flex-wrap gap-4">
                            @auth
                                <a href="{{ route('dashboard') }}" class="rounded-md bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-600">
                                    Go to Dashboard
                                </a>
                            @else
                                <a href="{{ route('register') }}" class="rounded-md bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-600">
                                    Create Account
                                </a>
                                <a href="{{ route('login') }}" class="rounded-md border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                                    Sign In
                                </a>
                            @endauth
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900">What VerifyHomes is solving</h2>
                        <div class="mt-6 space-y-4">
                            <div class="rounded-xl bg-slate-50 p-4">
                                <h3 class="font-medium text-slate-900">Verified listings</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    Listings are structured around documented landlords and property checks, starting with Akure and nearby areas.
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <h3 class="font-medium text-slate-900">Inspect first</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    The platform is built around physical inspection before commitment, helping tenants avoid rushed decisions.
                                </p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <h3 class="font-medium text-slate-900">Safer payments, less stress</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    Clear rental workflows support safer payments and reduce the uncertainty and pressure that agents can add to the process.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </body>
</html>
