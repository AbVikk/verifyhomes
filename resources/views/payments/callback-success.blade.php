<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Payment confirmed | {{ config('app.name', 'VerifyHomes') }}</title>
        @vite(['resources/css/app.css', 'resources/js/payment-return-success.js'])
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900">
        <main
            class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-12"
            data-payment-return-success
            data-return-url="{{ $returnUrl }}"
        >
            <section class="w-full rounded-2xl border border-emerald-200 bg-white p-8 text-center shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-700">VerifyHomes payment</p>
                <h1 class="mt-3 text-2xl font-semibold text-slate-950">Payment confirmed</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">Your payment has been verified by VerifyHomes. We are returning you to your updated payment result now.</p>
                <p class="mt-4 text-sm font-medium text-slate-700">Payment confirmed. You can return to VerifyHomes.</p>
                <a href="{{ $returnUrl }}" class="mt-6 inline-flex rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">{{ $returnLabel }}</a>
                <p class="mt-4 text-xs text-slate-500">Reference: {{ $transaction->reference }}</p>
            </section>
        </main>
    </body>
</html>
