<x-guest-layout>
    <div class="space-y-6">
        <div class="space-y-2 text-center">
            <h1 class="text-2xl font-semibold text-gray-900">Choose your account type</h1>
            <p class="text-sm text-gray-600">
                Start as a tenant if you want to search for trusted rentals, or register as a landlord to begin your verification journey.
            </p>
        </div>

        <div class="grid gap-4">
            <a href="{{ route('register.tenant') }}" class="block rounded-lg border border-gray-200 px-5 py-4 text-left transition hover:border-emerald-500 hover:bg-emerald-50">
                <span class="block text-base font-medium text-gray-900">Register as Tenant</span>
                <span class="mt-1 block text-sm text-gray-600">
                    Create a tenant account and get access to the renter side of VerifyHomes.
                </span>
            </a>

            <a href="{{ route('register.landlord') }}" class="block rounded-lg border border-gray-200 px-5 py-4 text-left transition hover:border-emerald-500 hover:bg-emerald-50">
                <span class="block text-base font-medium text-gray-900">Register as Landlord</span>
                <span class="mt-1 block text-sm text-gray-600">
                    Create a landlord account and start the verification process for your profile and listings.
                </span>
            </a>
        </div>

        <div class="flex items-center justify-end">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already have an account? Log in') }}
            </a>
        </div>
    </div>
</x-guest-layout>
