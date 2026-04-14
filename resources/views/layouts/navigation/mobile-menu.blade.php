@php
    $profileRoute = route('profile.edit');
    $user = Auth::user();

    if ($user?->isLandlord()) {
        $profileRoute = route('landlord.profile');
    } elseif ($user?->isTenant()) {
        $profileRoute = route('tenant.profile');
    }
@endphp

<div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
    <div class="pt-2 pb-3 space-y-1">
        @foreach ($navigationLinks as $link)
            <x-responsive-nav-link :href="$link['href']" :active="$link['active']">
                {{ __($link['label']) }}
            </x-responsive-nav-link>
        @endforeach

        @guest
            <x-responsive-nav-link :href="route('properties.index')" :active="request()->routeIs('properties.*')">
                {{ __('Browse Properties') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('login')" :active="request()->routeIs('login')">
                {{ __('Log In') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('register.tenant')" :active="request()->routeIs('register.tenant')">
                {{ __('Register as Tenant') }}
            </x-responsive-nav-link>
        @endguest
    </div>

    @auth
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="$profileRoute">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit" class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    @endauth
</div>
