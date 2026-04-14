@php($usesAdminShell = auth()->user()?->hasAnyRole(['admin', 'staff']))

@if ($usesAdminShell)
    <x-admin-layout pageHeading="Profile">
        <div class="admin-page">
            <div class="admin-page-inner">
                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)]">
                    <div class="space-y-6">
                        <x-admin.panel>
                            <div class="max-w-2xl">
                                @include('profile.partials.update-profile-information-form')
                            </div>
                        </x-admin.panel>

                        <x-admin.panel>
                            <div class="max-w-2xl" id="settings">
                                @include('profile.partials.update-password-form')
                            </div>
                        </x-admin.panel>
                    </div>

                    <div class="space-y-6">
                        <x-admin.panel>
                            <div class="space-y-4">
                                <div>
                                    <p class="admin-eyebrow">Account overview</p>
                                    <h2 class="admin-panel-title">Admin profile controls</h2>
                                    <p class="admin-panel-copy">Update your account details, rotate your password, or remove the account if it is no longer needed.</p>
                                </div>

                                <div class="admin-data-box space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Signed in as</p>
                                    <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                    <p class="text-sm text-slate-600">{{ $user->email }}</p>
                                </div>
                            </div>
                        </x-admin.panel>

                        <x-admin.panel>
                            <div class="max-w-xl">
                                @include('profile.partials.delete-user-form')
                            </div>
                        </x-admin.panel>
                    </div>
                </div>
            </div>
        </div>
    </x-admin-layout>
@else
    <x-app-layout>
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Profile') }}
            </h2>
        </x-slot>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </x-app-layout>
@endif
