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
                    <form wire:submit="save" class="space-y-8">
                        <div>
                            <p class="admin-eyebrow">Landlord profile</p>
                            <h2 class="admin-panel-title">Account and profile details</h2>
                            <p class="admin-panel-copy">
                                Your name and email stay under your main account. This page handles the landlord profile details, your workspace phone number, and the profile picture used for your landlord identity.
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
                                <p class="admin-help">This stays on your main user account and is not unique in the current schema.</p>
                                @error('accountPhone') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="businessName" class="admin-label">Business or landlord display name</label>
                                <input wire:model.defer="businessName" id="businessName" type="text" class="admin-control" />
                                <p class="admin-help">Use this if you operate under a business name or a public-facing landlord name.</p>
                                @error('businessName') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="residentialAddress" class="admin-label">Residential address</label>
                                <textarea wire:model.defer="residentialAddress" id="residentialAddress" rows="3" class="admin-control admin-control-textarea"></textarea>
                                @error('residentialAddress') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="city" class="admin-label">City or town</label>
                                <input wire:model.defer="city" id="city" type="text" class="admin-control" />
                                @error('city') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="state" class="admin-label">State</label>
                                <input wire:model.defer="state" id="state" type="text" class="admin-control" />
                                @error('state') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="whatsappNumber" class="admin-label">WhatsApp number</label>
                                <input wire:model.defer="whatsappNumber" id="whatsappNumber" type="text" class="admin-control" />
                                @error('whatsappNumber') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="occupationOrBusiness" class="admin-label">Occupation or business activity</label>
                                <input wire:model.defer="occupationOrBusiness" id="occupationOrBusiness" type="text" class="admin-control" />
                                <p class="admin-help">Describe what you do day to day, even if it differs from your public-facing landlord name.</p>
                                @error('occupationOrBusiness') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="shortBioOrNotes" class="admin-label">Short bio or notes</label>
                                <textarea wire:model.defer="shortBioOrNotes" id="shortBioOrNotes" rows="4" class="admin-control admin-control-textarea"></textarea>
                                @error('shortBioOrNotes') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-6 border-t border-slate-200 pt-6 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <p class="admin-eyebrow">Payout-ready account</p>
                                <p class="admin-panel-copy">Keep the payout account details on file now so future rent-payment settlements have a clear destination.</p>
                            </div>

                            <div>
                                <label for="bankName" class="admin-label">Bank name</label>
                                <input wire:model.defer="bankName" id="bankName" type="text" class="admin-control" />
                                <p class="admin-help">Use the receiving bank name exactly as the account is registered.</p>
                                @error('bankName') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="accountName" class="admin-label">Account name</label>
                                <input wire:model.defer="accountName" id="accountName" type="text" class="admin-control" />
                                <p class="admin-help">This should match the payout-ready account holder name.</p>
                                @error('accountName') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="accountNumber" class="admin-label">Account number</label>
                                <input wire:model.defer="accountNumber" id="accountNumber" type="text" inputmode="numeric" class="admin-control" />
                                <p class="admin-help">Use digits only. This is stored for future payout handling, not automatic payout in this pass.</p>
                                @error('accountNumber') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-slate-200 pt-5 md:flex-row md:items-center md:justify-between">
                            <p class="text-sm text-slate-600">
                                Verification status:
                                <span class="font-medium text-slate-900">{{ str($verificationStatus)->headline() }}</span>
                            </p>

                            <button type="submit" wire:loading.attr="disabled" wire:target="save,profilePicture" class="admin-button admin-button-primary">
                                <span wire:loading.remove wire:target="save,profilePicture">Save Profile</span>
                                <span wire:loading wire:target="save,profilePicture">Saving...</span>
                            </button>
                        </div>
                    </form>
                </x-admin.panel>
            </div>

            <x-admin.panel>
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Identity photo</p>
                        <h3 class="admin-panel-title">Profile picture</h3>
                        <p class="admin-panel-copy">
                            Upload a profile picture from your device or use your camera if the browser supports it. If camera access is unavailable, the regular file upload continues to work.
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
                                <p>Current picture is saved and active for your landlord profile.</p>
                            @else
                                <p>No profile picture uploaded yet.</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label for="profilePicture" class="admin-label">Upload profile picture</label>
                        <input wire:model="profilePicture" id="profilePicture" data-profile-picture-input type="file" accept="image/png,image/jpeg,image/webp" class="admin-control file:mr-4 file:border-0 file:bg-transparent file:px-0 file:py-0 file:text-sm file:font-medium" />
                        <div wire:loading wire:target="profilePicture" class="admin-help">Preparing image...</div>
                        <p class="admin-help">Accepted file types: JPG, JPEG, PNG, WEBP up to 5MB.</p>
                        @error('profilePicture') <p class="admin-error">{{ $message }}</p> @enderror
                    </div>

                    <div wire:ignore data-profile-camera-root class="space-y-3 rounded-2xl border border-slate-200 p-4">
                        <div class="flex flex-wrap gap-3">
                            <button type="button" data-profile-camera-start class="admin-button admin-button-secondary">
                                Use Camera
                            </button>
                            <button type="button" data-profile-camera-capture class="admin-button admin-button-primary hidden">
                                Capture Photo
                            </button>
                            <button type="button" data-profile-camera-stop class="admin-action-link hidden">
                                Stop Camera
                            </button>
                        </div>

                        <p data-profile-camera-status class="text-sm text-slate-600">
                            Camera capture is optional. If camera access is blocked or unsupported, upload a picture from your device instead.
                        </p>

                        <video data-profile-camera-preview class="hidden w-full rounded-2xl border border-slate-200 bg-slate-950/90" autoplay playsinline muted></video>
                        <canvas data-profile-camera-canvas class="hidden"></canvas>
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
