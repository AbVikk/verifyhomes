<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        @if (session('status')) <x-admin.alert>{{ session('status') }}</x-admin.alert> @endif
        <x-admin.panel>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div><p class="admin-eyebrow">Tenancy agreement</p><h2 class="admin-panel-title">Rental agreement review</h2><p class="admin-panel-copy">Review the agreement recorded from the confirmed rent payment before accepting it.</p></div>
                <button type="button" onclick="window.print()" class="admin-button admin-button-secondary print:hidden">Print agreement</button>
            </div>
        </x-admin.panel>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <x-admin.panel><x-tenancy-agreement-details :agreement="$agreement" /></x-admin.panel>
            <x-admin.panel>
                <div class="space-y-4">
                    <div><p class="admin-eyebrow">Acceptance</p><h3 class="text-lg font-semibold text-slate-950">Agreement status</h3></div>
                    @if ($agreement->completed_at)
                        <x-status-chip tone="success">Completed</x-status-chip><p class="text-sm text-slate-600">Completed {{ $agreement->completed_at->format('M j, Y g:i A') }}.</p>
                    @elseif ($agreement->landlord_accepted_at)
                        <x-status-chip tone="info">Awaiting tenant</x-status-chip><p class="text-sm text-slate-600">You accepted on {{ $agreement->landlord_accepted_at->format('M j, Y g:i A') }}. The agreement completes when the tenant accepts.</p>
                    @else
                        <x-status-chip tone="{{ $agreement->tenant_accepted_at ? 'warning' : 'info' }}">{{ $agreement->tenant_accepted_at ? 'Awaiting your acceptance' : 'Awaiting tenant' }}</x-status-chip>
                        <form wire:submit="acceptAgreement" class="space-y-4">
                            <label class="flex gap-3 text-sm text-slate-700"><input type="checkbox" wire:model="accepted" class="mt-1 rounded border-slate-300 text-sky-600 focus:ring-sky-500"><span>I have read and agree to this tenancy agreement.</span></label>
                            @error('accepted') <p class="admin-error">{{ $message }}</p> @enderror
                            <button type="submit" class="admin-button admin-button-primary w-full" wire:loading.attr="disabled" wire:target="acceptAgreement"><span wire:loading.remove wire:target="acceptAgreement">Accept agreement</span><span wire:loading wire:target="acceptAgreement">Recording acceptance...</span></button>
                        </form>
                    @endif
                </div>
            </x-admin.panel>
        </div>
    </div>
</div>
