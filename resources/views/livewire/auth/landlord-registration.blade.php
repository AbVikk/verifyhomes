<x-guest-layout>
    <form wire:submit="register" class="space-y-4">
        <div class="space-y-2 text-center">
            <h1 class="text-2xl font-semibold text-gray-900">Landlord registration</h1>
            <p class="text-sm text-gray-600">
                Create your landlord account to begin profile verification and prepare for future property onboarding.
            </p>
        </div>

        <div>
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input id="name" wire:model="name" class="mt-1 block w-full" type="text" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" wire:model="email" class="mt-1 block w-full" type="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" wire:model="password" class="mt-1 block w-full" type="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" wire:model="password_confirmation" class="mt-1 block w-full" type="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="text-sm text-gray-600 underline hover:text-gray-900" href="{{ route('register') }}">
                Back to account type selection
            </a>

            <x-primary-button>
                Create Landlord Account
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
