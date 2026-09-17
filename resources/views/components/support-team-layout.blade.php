<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>VerifyHomes Support</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="bg-slate-100 font-sans text-slate-900">
<div x-data="{ open:false }" class="min-h-screen lg:flex">
    <aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-30 flex w-72 flex-col border-r border-slate-800 bg-slate-950 text-white transition-transform lg:static lg:translate-x-0">
        <div class="border-b border-white/10 px-6 py-5"><p class="text-sm font-semibold">VerifyHomes Support</p><p class="mt-1 text-xs text-slate-400">Support workspace</p></div>
        <nav class="flex-1 space-y-2 px-4 py-6"><a href="{{ route('support-team.dashboard') }}" class="block rounded-lg px-4 py-3 text-sm hover:bg-white/10">Dashboard</a><a href="{{ route('support-team.requests.index') }}" class="block rounded-lg px-4 py-3 text-sm hover:bg-white/10">Support Requests</a><a href="{{ route('support-team.requests.index', ['assignment' => 'mine']) }}" class="block rounded-lg px-4 py-3 text-sm hover:bg-white/10">Assigned to me</a><a href="{{ route('support-team.requests.index', ['assignment' => 'unassigned']) }}" class="block rounded-lg px-4 py-3 text-sm hover:bg-white/10">Unassigned</a></nav>
        <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10 p-4">@csrf<button class="w-full rounded-lg border border-white/15 px-4 py-3 text-left text-sm hover:bg-white/10">Log out</button></form>
    </aside>
    <div class="min-w-0 flex-1"><header class="flex items-center gap-4 border-b border-slate-200 bg-white px-4 py-4 lg:px-8"><button @click="open=true" class="rounded-md border border-slate-300 p-2 lg:hidden" aria-label="Open navigation">☰</button><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">Support operations</p><h1 class="text-lg font-semibold text-slate-950">{{ $heading ?? 'VerifyHomes Support' }}</h1></div></header><main class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">{{ $slot }}</main></div>
</div>
</body>
</html>
