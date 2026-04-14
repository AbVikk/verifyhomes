@props([
    'brandTitle',
    'homeHref',
    'profileHref' => route('profile.edit'),
    'roleLabel',
    'navigationLinks' => [],
    'pageHeading' => 'Dashboard',
    'shellKey' => 'dashboard',
    'menuTitle' => 'Workspace Menu',
    'menuCopy' => 'Move through your operational workspace from one consistent dashboard shell.',
])

@php
    $user = Auth::user();
    $nameParts = collect(preg_split('/\s+/', trim($user?->name ?? '')))
        ->filter()
        ->values();
    $userInitials = $nameParts->count() >= 2
        ? mb_strtoupper(mb_substr($nameParts->first(), 0, 1).mb_substr($nameParts->last(), 0, 1))
        : mb_strtoupper(mb_substr($nameParts->first() ?? 'U', 0, 1));
    $sidebarCollapsed = request()->cookie("{$shellKey}.sidebar.collapsed") === 'true';
    $notificationsAvailable = $user
        && class_exists(\App\Models\UserNotification::class)
        && \Illuminate\Support\Facades\Schema::hasTable('user_notifications');
    $notifications = $notificationsAvailable
        ? \App\Models\UserNotification::forUser($user->getKey())->latest()->take(5)->get()
        : collect();
    $unreadNotifications = $notificationsAvailable
        ? \App\Models\UserNotification::forUser($user->getKey())->whereNull('read_at')->count()
        : 0;
    $quickSearchRoute = $shellKey === 'landlord' && \Illuminate\Support\Facades\Route::has('landlord.search')
        ? route('landlord.search')
        : null;
    $notificationsIndexRoute = match ($shellKey) {
        'landlord' => \Illuminate\Support\Facades\Route::has('landlord.notifications.index') ? route('landlord.notifications.index') : null,
        'tenant' => \Illuminate\Support\Facades\Route::has('tenant.notifications.index') ? route('tenant.notifications.index') : null,
        default => null,
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'VerifyHomes') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
        @livewireStyles
    </head>
    <body class="bg-slate-100 font-sans antialiased text-slate-900 overflow-hidden">
        <div
            data-admin-shell
            data-admin-shell-key="{{ $shellKey }}"
            data-admin-sidebar-collapsed="{{ $sidebarCollapsed ? 'true' : 'false' }}"
            class="admin-shell"
        >
            <div
                data-admin-overlay
                class="fixed inset-0 z-40 hidden bg-slate-950/40 lg:hidden"
            ></div>

            <aside
                data-admin-sidebar
                class="admin-sidebar fixed inset-y-0 left-0 z-50 flex w-80 max-w-[85vw] -translate-x-full flex-col border-r border-slate-800 bg-slate-950 text-slate-100 shadow-2xl transition-transform duration-200 ease-out lg:translate-x-0 lg:max-w-none lg:shadow-none"
            >
                <div class="flex items-center justify-between border-b border-white/10 px-6 py-5">
                    <a href="{{ $homeHref }}" class="admin-sidebar-brand flex min-w-0 items-center gap-3">
                        <x-application-logo class="admin-sidebar-logo h-11 w-auto shrink-0 text-white" />
                        <div class="admin-sidebar-brand-copy min-w-0" data-admin-sidebar-text>
                            <p class="truncate text-sm font-semibold tracking-wide text-white">{{ $brandTitle }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $roleLabel }}</p>
                        </div>
                    </a>

                    <button
                        type="button"
                        class="inline-flex items-center rounded-md p-2 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden"
                        data-admin-sidebar-close
                    >
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="admin-sidebar-scroll flex-1 overflow-y-auto px-4 py-6">
                    <div class="admin-sidebar-summary rounded-2xl border border-white/10 bg-white/5 p-4" data-admin-sidebar-summary>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $menuTitle }}</p>
                        <p class="mt-2 text-sm text-slate-300">{{ $menuCopy }}</p>
                    </div>

                    <nav class="mt-6 space-y-2">
                        @foreach ($navigationLinks as $link)
                            <a
                                href="{{ $link['href'] }}"
                                title="{{ $link['label'] }}"
                                class="{{ $link['active'] ? 'border border-sky-300/20 bg-sky-300/10 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]' : 'border border-transparent text-slate-300 hover:bg-white/5 hover:text-white' }} admin-sidebar-link group relative flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition"
                            >
                                @if ($link['active'])
                                    <span class="absolute inset-y-3 left-1 w-1 rounded-full bg-sky-300/80"></span>
                                @endif

                                <span class="{{ $link['active'] ? 'border border-sky-200/20 bg-white/10 text-white' : 'bg-white/10 text-slate-300 group-hover:bg-white/15 group-hover:text-white' }} admin-sidebar-link-icon inline-flex h-10 w-10 items-center justify-center rounded-xl transition">
                                    @switch($link['icon'] ?? 'dashboard')
                                        @case('dashboard')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75L12 3l8.25 6.75v9a2.25 2.25 0 01-2.25 2.25h-12A2.25 2.25 0 013.75 18.75v-9z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 21V12.75h4.5V21" />
                                            </svg>
                                            @break
                                        @case('documents')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h6l4.5 4.5v10.5A2.25 2.25 0 0115.75 21h-8.5A2.25 2.25 0 015 18.75V6A2.25 2.25 0 017.25 3.75h.25z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 3.75V8.25H18" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12h7.5M8.25 15.75h7.5" />
                                            </svg>
                                            @break
                                        @case('profile')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5a7.5 7.5 0 0115 0" />
                                            </svg>
                                            @break
                                        @case('properties')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 21V7.5L12 3l6.75 4.5V21" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 9.75h.008v.008H9V9.75zm0 3.75h.008v.008H9V13.5zm0 3.75h.008v.008H9v-.008zm6-7.5h.008v.008H15V9.75zm0 3.75h.008v.008H15V13.5z" />
                                            </svg>
                                            @break
                                        @case('inspection-requests')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75V6m7.5-2.25V6M4.5 8.25h15" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 4.5h10.5A2.25 2.25 0 0119.5 6.75v10.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 17.25V6.75A2.25 2.25 0 016.75 4.5z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 12h4.5M9.75 15h2.25" />
                                            </svg>
                                            @break
                                        @case('browse-properties')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 21V7.5L12 3l6.75 4.5V21" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25l2.25 2.25L16.5 9.75" />
                                            </svg>
                                            @break
                                        @case('payments')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5A2.25 2.25 0 016 5.25h12A2.25 2.25 0 0120.25 7.5v9A2.25 2.25 0 0118 18.75H6A2.25 2.25 0 013.75 16.5v-9z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25h3" />
                                            </svg>
                                            @break
                                        @case('notifications')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a3 3 0 006 0" />
                                            </svg>
                                            @break
                                        @case('occupancy')
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 21h15" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 21V9.75L12 6l5.25 3.75V21" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 21v-5.25h4.5V21" />
                                            </svg>
                                            @break
                                        @default
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75L12 3l8.25 6.75v9a2.25 2.25 0 01-2.25 2.25h-12A2.25 2.25 0 013.75 18.75v-9z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 21V12.75h4.5V21" />
                                            </svg>
                                    @endswitch
                                </span>
                                <span class="admin-sidebar-link-label truncate" data-admin-sidebar-text>{{ $link['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>

                @auth
                    <div class="border-t border-white/10 px-4 py-5">
                        <div class="admin-sidebar-account rounded-2xl bg-white/5 p-4">
                            <div class="flex items-center gap-3">
                                <div class="admin-sidebar-account-avatar flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 text-sm font-semibold text-white">
                                    {{ str(Auth::user()->name)->substr(0, 2)->upper() }}
                                </div>
                                <div class="admin-sidebar-account-copy min-w-0" data-admin-sidebar-text>
                                    <p class="truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ Auth::user()->email }}</p>
                                </div>
                            </div>

                            <div class="admin-sidebar-account-actions mt-4 flex gap-2">
                                <a href="{{ $profileHref }}" class="inline-flex flex-1 items-center justify-center rounded-xl border border-white/10 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-white/5">
                                    <span data-admin-sidebar-text>Profile</span>
                                    <span class="hidden" data-admin-sidebar-icon-only aria-hidden="true">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 19.5a7.5 7.5 0 0115 0" />
                                        </svg>
                                    </span>
                                </a>
                                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                                    @csrf
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-slate-100 hover:bg-white/10">
                                        <span data-admin-sidebar-text>Log Out</span>
                                        <span class="hidden" data-admin-sidebar-icon-only aria-hidden="true">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H9.75m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endauth
            </aside>

            <div class="admin-main">
                <header class="admin-topbar">
                    <div class="admin-topbar-inner">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="admin-topbar-toggle"
                                data-admin-sidebar-toggle
                            >
                                <span class="sr-only">Toggle sidebar collapse</span>
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15M4.5 12h15M4.5 17.25h15" />
                                </svg>
                            </button>

                            <div class="min-w-0">
                                <p class="admin-topbar-kicker">{{ $pageHeading }}</p>
                                <div class="flex min-w-0 items-center gap-3">
                                    @auth
                                        <h1 class="admin-topbar-title truncate">{{ Auth::user()->name }}</h1>
                                    @else
                                        <h1 class="admin-topbar-title truncate">{{ $brandTitle }}</h1>
                                    @endauth
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            @if ($quickSearchRoute)
                                <form method="GET" action="{{ $quickSearchRoute }}" class="hidden w-64 lg:block">
                                    <label class="sr-only" for="quick-search">Search</label>
                                    <div class="relative">
                                        <input
                                            id="quick-search"
                                            name="q"
                                            type="search"
                                            placeholder="Search listings, tenants, payments"
                                            class="admin-control admin-control-select h-10 w-full rounded-full border border-slate-200 bg-white/80 pl-4 pr-10 text-sm text-slate-700 shadow-sm focus:border-slate-300 focus:ring-0"
                                        />
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 19a8 8 0 100-16 8 8 0 000 16z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35" />
                                            </svg>
                                        </span>
                                    </div>
                                </form>
                            @endif

                            @if ($notificationsAvailable)
                                <div class="admin-topbar-profile" data-admin-notifications>
                                    <button type="button" class="admin-topbar-profile-trigger" data-admin-notifications-toggle aria-expanded="false">
                                        <span class="relative inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a3 3 0 006 0" />
                                            </svg>
                                            @if ($unreadNotifications > 0)
                                                <span class="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">
                                                    {{ $unreadNotifications }}
                                                </span>
                                            @endif
                                        </span>
                                    </button>

                                    <div class="admin-topbar-dropdown hidden" data-admin-notifications-menu>
                                        <div class="admin-topbar-dropdown-header">
                                            <p class="admin-topbar-dropdown-name">Notifications</p>
                                            <p class="admin-topbar-dropdown-email">{{ $unreadNotifications > 0 ? "{$unreadNotifications} unread updates" : 'All caught up' }}</p>
                                        </div>
                                        <div class="admin-topbar-dropdown-links">
                                            @forelse ($notifications as $notification)
                                                <div class="admin-topbar-dropdown-link">
                                                    <p class="text-sm font-medium text-slate-900">{{ $notification->title }}</p>
                                                    @if ($notification->body)
                                                        <p class="mt-1 text-xs text-slate-500">{{ $notification->body }}</p>
                                                    @endif
                                                    @if ($notification->link)
                                                        <a href="{{ $notification->link }}" class="mt-2 inline-flex text-xs font-semibold text-sky-700">Open</a>
                                                    @endif
                                                </div>
                                            @empty
                                                <div class="admin-topbar-dropdown-link">
                                                    <p class="text-sm text-slate-600">No notifications yet.</p>
                                                </div>
                                            @endforelse
                                        </div>
                                        @if ($notificationsIndexRoute)
                                            <div class="admin-topbar-dropdown-footer">
                                                <a href="{{ $notificationsIndexRoute }}" class="admin-topbar-dropdown-link">View all notifications</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @auth
                                <div class="admin-topbar-profile" data-admin-profile>
                                    <button type="button" class="admin-topbar-profile-trigger" data-admin-profile-toggle aria-expanded="false">
                                        <span class="admin-topbar-profile-avatar">
                                            {{ $userInitials }}
                                        </span>
                                        <svg class="h-4 w-4 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    <div class="admin-topbar-dropdown hidden" data-admin-profile-menu>
                                        <div class="admin-topbar-dropdown-header">
                                            <p class="admin-topbar-dropdown-name">{{ Auth::user()->name }}</p>
                                            <p class="admin-topbar-dropdown-email">{{ Auth::user()->email }}</p>
                                        </div>
                                        <div class="admin-topbar-dropdown-links">
                                            <a href="{{ $profileHref }}" class="admin-topbar-dropdown-link">Profile</a>
                                        </div>
                                        <div class="admin-topbar-dropdown-footer">
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit" class="admin-topbar-dropdown-link admin-topbar-dropdown-danger">
                                                    Log Out
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endauth

                            <button
                                type="button"
                                class="admin-topbar-mobile-toggle lg:hidden"
                                data-admin-sidebar-open
                            >
                                <span class="sr-only">Open sidebar</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M2.75 5A.75.75 0 013.5 4.25h13a.75.75 0 010 1.5h-13A.75.75 0 012.75 5zm0 5A.75.75 0 013.5 9.25h13a.75.75 0 010 1.5h-13A.75.75 0 012.75 10zm0 5a.75.75 0 01.75-.75h13a.75.75 0 010 1.5h-13a.75.75 0 01-.75-.75z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </header>

                <div class="admin-content-scroll">
                    <main class="admin-content">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
