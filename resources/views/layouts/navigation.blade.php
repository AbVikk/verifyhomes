@php
    $user = Auth::user();
    $dashboardRoute = route('dashboard');
    $navigationLinks = [];

    if ($user) {
        $dashboardRoute = route($user->dashboardRouteName());

        if ($user->hasAnyRole(['admin', 'staff'])) {
            $navigationLinks = [
                [
                    'label' => 'Admin Dashboard',
                    'href' => route('admin.dashboard'),
                    'active' => request()->routeIs('admin.dashboard'),
                ],
                [
                    'label' => 'Landlords',
                    'href' => route('admin.landlords.index'),
                    'active' => request()->routeIs('admin.landlords.*'),
                ],
                [
                    'label' => 'Properties',
                    'href' => route('admin.properties.index'),
                    'active' => request()->routeIs('admin.properties.*'),
                ],
                [
                    'label' => 'Inspection Requests',
                    'href' => route('admin.inspection-requests.index'),
                    'active' => request()->routeIs('admin.inspection-requests.*'),
                ],
            ];
        } else {
            $navigationLinks[] = [
                'label' => 'Dashboard',
                'href' => $dashboardRoute,
                'active' => request()->routeIs('dashboard') || request()->routeIs('landlord.dashboard') || request()->routeIs('tenant.dashboard'),
            ];

            if ($user->isLandlord()) {
                $navigationLinks[] = [
                    'label' => 'Landlord Profile',
                    'href' => route('landlord.profile'),
                    'active' => request()->routeIs('landlord.profile'),
                ];
                $navigationLinks[] = [
                    'label' => 'Documents',
                    'href' => route('landlord.documents'),
                    'active' => request()->routeIs('landlord.documents'),
                ];
                $navigationLinks[] = [
                    'label' => 'Inspection Requests',
                    'href' => route('landlord.inspection-requests.index'),
                    'active' => request()->routeIs('landlord.inspection-requests.*'),
                ];
                $navigationLinks[] = [
                    'label' => 'Properties',
                    'href' => route('landlord.properties'),
                    'active' => request()->routeIs('landlord.properties') || request()->routeIs('landlord.properties.create') || request()->routeIs('landlord.properties.edit'),
                ];
            }

            if ($user->isTenant()) {
                $navigationLinks[] = [
                    'label' => 'Inspection Requests',
                    'href' => route('tenant.inspection-requests.index'),
                    'active' => request()->routeIs('tenant.inspection-requests.*'),
                ];
                $navigationLinks[] = [
                    'label' => 'Browse Properties',
                    'href' => route('properties.index'),
                    'active' => request()->routeIs('properties.*'),
                ];
            }
        }
    }
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ $user ? $dashboardRoute : route('properties.index') }}" class="flex items-center">
                        <x-application-logo class="h-11 w-auto sm:h-12" />
                    </a>
                </div>

                @include('layouts.navigation.desktop-links', ['navigationLinks' => $navigationLinks])
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @auth
                    @include('layouts.navigation.user-menu')
                @else
                    @include('layouts.navigation.guest-actions')
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    @include('layouts.navigation.mobile-menu', ['navigationLinks' => $navigationLinks])
</nav>
