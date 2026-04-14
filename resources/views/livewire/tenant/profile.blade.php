@php($user = auth()->user())
@php($status = session('status'))

<div class="admin-page">
    <div class="admin-page-inner">
        @if ($status && ! in_array($status, ['profile-updated', 'password-updated'], true))
            <div class="admin-flash-success">
                {{ $status }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,0.78fr)_minmax(320px,0.42fr)]">
            <div class="space-y-6">
                <x-admin.panel>
                    <div class="space-y-6">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    <div class="space-y-6" id="settings">
                        @include('profile.partials.update-password-form')
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    <div class="space-y-6">
                        @include('profile.partials.delete-user-form')
                    </div>
                </x-admin.panel>

                <x-admin.panel>
                    @if (! $tenantProfilesAvailable)
                        <x-admin.empty-state
                            title="Tenant profile details are not available yet."
                            copy="This page will populate automatically after the tenant profile table is available in this environment."
                        />
                    @else
                        <form wire:submit="save" class="space-y-8">
                            <div>
                                <p class="admin-eyebrow">Tenant profile</p>
                                <h2 class="admin-panel-title">Account and workspace details</h2>
                                <p class="admin-panel-copy">
                                    Your name and email stay on your main account. Use this page to keep your tenant workspace details current so saved listings, payments, and inspection requests stay tied to the right profile information.
                                </p>
                            </div>

                            <div class="grid gap-6 md:grid-cols-2">
                                <div>
                                    <label class="admin-label">Full name</label>
                                    <input type="text" value="{{ $name }}" disabled class="admin-control bg-slate-100/80 text-slate-500" />
                                </div>

                                <div>
                                    <label class="admin-label">Email address</label>
                                    <input type="email" value="{{ $email }}" disabled class="admin-control bg-slate-100/80 text-slate-500" />
                                </div>

                                <div>
                                    <label for="accountPhone" class="admin-label">Account phone number</label>
                                    <input wire:model.defer="accountPhone" id="accountPhone" type="text" class="admin-control" />
                                    <p class="admin-help">This phone number stays on your main user account and supports tenant contact history across the workspace.</p>
                                    @error('accountPhone') <p class="admin-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="occupation" class="admin-label">Occupation</label>
                                    <input wire:model.defer="occupation" id="occupation" type="text" class="admin-control" />
                                    <p class="admin-help">Optional, but useful if you want your tenant profile to stay complete for later workflows.</p>
                                    @error('occupation') <p class="admin-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="residentialAddress" class="admin-label">Residential address</label>
                                    <textarea wire:model.defer="residentialAddress" id="residentialAddress" rows="3" class="admin-control admin-control-textarea"></textarea>
                                    @error('residentialAddress') <p class="admin-error">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="gender" class="admin-label">Gender</label>
                                    <select wire:model.defer="gender" id="gender" class="admin-control admin-control-select">
                                        <option value="">Prefer not to say</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    @error('gender') <p class="admin-error">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="flex items-center justify-end border-t border-slate-200 pt-5">
                                <button type="submit" wire:loading.attr="disabled" wire:target="save,profilePicture" class="admin-button admin-button-primary">
                                    <span wire:loading.remove wire:target="save,profilePicture">Save Profile</span>
                                    <span wire:loading wire:target="save,profilePicture">Saving...</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </x-admin.panel>
            </div>

            <x-admin.panel>
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Identity photo</p>
                        <h3 class="admin-panel-title">Profile picture</h3>
                        <p class="admin-panel-copy">
                            Keep one workspace profile picture for your tenant activity. It appears consistently across your tenant shell account controls.
                        </p>
                    </div>

                    <div class="flex flex-col items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center">
                        <div class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                            @if ($profilePicture)
                                <img src="{{ $profilePicture->temporaryUrl() }}" alt="Selected profile picture preview" class="h-full w-full object-cover">
                            @elseif ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="Current profile picture" class="h-full w-full object-cover">
                            @else
                                <span class="text-3xl font-semibold text-slate-400">{{ str($name)->substr(0, 2)->upper() }}</span>
                            @endif
                        </div>

                        <div class="space-y-2 text-sm text-slate-600">
                            @if ($profilePicture)
                                <p>Selected file: {{ $profilePicture->getClientOriginalName() }}</p>
                            @elseif ($avatarPath)
                                <p>Your current picture is active in the tenant workspace.</p>
                            @else
                                <p>No profile picture uploaded yet.</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label for="profilePicture" class="admin-label">Upload profile picture</label>
                        <input wire:model="profilePicture" id="profilePicture" type="file" accept="image/png,image/jpeg,image/webp" class="admin-control file:mr-4 file:border-0 file:bg-transparent file:px-0 file:py-0 file:text-sm file:font-medium" />
                        <div wire:loading wire:target="profilePicture" class="admin-help">Preparing image...</div>
                        <p class="admin-help">Accepted file types: JPG, JPEG, PNG, WEBP up to 5MB.</p>
                        @error('profilePicture') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-4">
                        @if ($profilePicture)
                            <button wire:click="clearSelectedProfilePicture" type="button" class="admin-button admin-button-secondary">
                                Clear Selected Picture
                            </button>
                        @endif

                        @if ($avatarPath)
                            <button wire:click="removeProfilePicture" type="button" class="admin-action-link">
                                Remove Current Picture
                            </button>
                        @endif
                    </div>
                </div>
            </x-admin.panel>
        </div>
    </div>
</div>
