<div class="admin-page">
    <div class="admin-page-inner max-w-3xl">
        @if (session('status')) <div class="admin-flash-success">{{ session('status') }}</div> @endif

        <x-admin.panel>
            <form wire:submit="submit" class="space-y-6">
                <div><p class="admin-eyebrow">Verify your identity</p><h2 class="admin-panel-title">Identity verification</h2><p class="admin-panel-copy">VerifyHomes verifies tenants before inspection and property payments to help keep the platform safe.</p></div>

                @if ($profile->verification_status === 'verified')
                    <div class="admin-alert admin-alert-success">Identity verified. Your identity details are securely on file.</div>
                @else
                    @if ($profile->verification_status === 'pending') <div class="admin-alert admin-alert-info">Verification submitted. VerifyHomes is reviewing your details.</div> @endif
                    @if ($profile->verification_status === 'rejected') <div class="admin-alert admin-alert-warning">{{ $profile->rejection_reason ?: 'Your verification needs attention.' }} Update and resubmit your information below.</div> @endif

                    @if ($reviewing)
                        @php($idIsImage = in_array(strtolower(pathinfo($idDocument->getClientOriginalName(), PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true))
                        <div class="space-y-5">
                            <div><p class="admin-eyebrow">Final review</p><h3 class="admin-panel-title">Check your verification details</h3><p class="admin-panel-copy">Confirm your masked details and selected files before private submission.</p></div>
                            <dl class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm md:grid-cols-2"><div><dt class="text-slate-500">ID type</dt><dd class="mt-1 font-medium text-slate-900">{{ str($idType)->headline() }}</dd></div><div><dt class="text-slate-500">ID number</dt><dd class="mt-1 font-medium text-slate-900">{{ str_repeat('*', max(0, strlen($idNumber) - 4)).substr($idNumber, -4) }}</dd></div></dl>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 p-4"><p class="font-medium text-slate-900">ID document</p><p class="mt-1 truncate text-sm text-slate-600">{{ $idDocument->getClientOriginalName() }}</p>@if ($idIsImage)<img src="{{ $idDocument->temporaryUrl() }}" alt="Selected ID document preview" class="mt-3 max-h-56 w-full rounded-xl border border-slate-200 object-contain">@else<div class="mt-3 rounded-xl bg-slate-100 p-5 text-sm text-slate-600">PDF selected. It will be stored privately for administrator review.</div>@endif</div>
                                <div class="rounded-2xl border border-slate-200 p-4"><p class="font-medium text-slate-900">Verification selfie</p><img src="{{ $selfie->temporaryUrl() }}" alt="Selected verification selfie preview" class="mt-3 max-h-56 w-full rounded-xl border border-slate-200 object-contain"></div>
                            </div>
                            <div class="admin-alert admin-alert-info"><p class="font-medium">Before you submit</p><p class="mt-1">Use good lighting, keep your face clearly visible, and make sure document text is readable. These are guidance checks only.</p></div>
                            <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-between"><button wire:click="editDetails" type="button" class="admin-button admin-button-secondary">Edit details</button><button wire:loading.attr="disabled" wire:target="submit" type="submit" class="admin-button admin-button-primary"><span wire:loading.remove wire:target="submit">Submit for verification</span><span wire:loading wire:target="submit">Submitting...</span></button></div>
                        </div>
                    @else
                        <div class="grid gap-5 md:grid-cols-2"><div><label for="idType" class="admin-label">ID type</label><select wire:model="idType" id="idType" class="admin-control"><option value="">Choose ID type</option><option value="nin">NIN</option><option value="international_passport">International Passport</option><option value="voters_card">Voter's Card</option></select>@error('idType') <p class="admin-error">{{ $message }}</p> @enderror</div><div><label for="idNumber" class="admin-label">ID number</label><input wire:model="idNumber" id="idNumber" type="text" class="admin-control" autocomplete="off">@error('idNumber') <p class="admin-error">{{ $message }}</p> @enderror</div></div>
                        <div><label for="idDocument" class="admin-label">ID document</label><input wire:model="idDocument" id="idDocument" type="file" accept="application/pdf,image/jpeg,image/png" class="admin-control"><div wire:loading wire:target="idDocument" class="admin-help">Preparing ID document...</div>@error('idDocument') <p class="admin-error">{{ $message }}</p> @enderror</div>
                        <div class="space-y-3"><label for="selfie" class="admin-label">Selfie</label><input wire:model="selfie" id="selfie" type="file" accept="image/jpeg,image/png" class="admin-control"><div wire:loading wire:target="selfie" class="admin-help">Preparing selfie...</div><x-camera-capture input-id="selfie" label="Take selfie" kind="selfie" />@if ($selfie)<img src="{{ $selfie->temporaryUrl() }}" alt="Selected selfie preview" class="h-32 w-32 rounded-xl border border-slate-200 object-cover">@endif @error('selfie') <p class="admin-error">{{ $message }}</p> @enderror<p class="admin-help">Use good lighting, keep your face centered and clearly visible, and avoid sunglasses or heavy obstructions. The original selfie framing is kept for private review.</p></div>
                        <div class="flex justify-end border-t border-slate-200 pt-5"><button wire:click="review" wire:loading.attr="disabled" wire:target="review,idDocument,selfie" type="button" class="admin-button admin-button-primary"><span wire:loading.remove wire:target="review,idDocument,selfie">Review details</span><span wire:loading wire:target="review,idDocument,selfie">Preparing review...</span></button></div>
                    @endif
                @endif
            </form>
        </x-admin.panel>
    </div>
</div>
