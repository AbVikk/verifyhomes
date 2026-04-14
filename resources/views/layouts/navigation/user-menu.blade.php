@php
    $profileRoute = route('profile.edit');
    $user = Auth::user();

    if ($user?->isLandlord()) {
        $profileRoute = route('landlord.profile');
    } elseif ($user?->isTenant()) {
        $profileRoute = route('tenant.profile');
    }
@endphp

<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
            <div>{{ Auth::user()->name }}</div>

            <div class="ms-1">
                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </div>
        </button>
    </x-slot>

    <x-slot name="content">
        <x-dropdown-link :href="$profileRoute">
            {{ __('Profile') }}
        </x-dropdown-link>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                {{ __('Log Out') }}
            </button>
        </form>
    </x-slot>
</x-dropdown>
