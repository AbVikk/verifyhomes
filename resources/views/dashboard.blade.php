<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('General Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="vh-surface">
                <div class="p-6 text-gray-900 space-y-3">
                    <h3 class="text-lg font-semibold text-gray-900">General dashboard fallback</h3>
                    <p>
                        Welcome back, {{ auth()->user()->name }}.
                    </p>
                    <p class="text-sm text-gray-600">
                        This page is the authenticated fallback dashboard. Your current role{{ auth()->user()->getRoleNames()->count() === 1 ? '' : 's' }}:
                        {{ auth()->user()->getRoleNames()->implode(', ') ?: 'none assigned' }}.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
