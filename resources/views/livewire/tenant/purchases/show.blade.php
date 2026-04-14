<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        <x-admin.panel>
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="admin-eyebrow">Purchase receipt</p>
                    <h2 class="admin-panel-title">Purchase summary</h2>
                    <p class="admin-panel-copy">This receipt confirms your verified purchase and the next step.</p>
                </div>
                <a href="{{ route('tenant.occupancy.index') }}" class="admin-button admin-button-secondary">Back to My Stays</a>
            </div>
        </x-admin.panel>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
            <x-admin.panel>
                <div class="space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                            @if ($purchase->property?->coverImage)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($purchase->property->coverImage->image_path) }}" alt="{{ $purchase->property?->title ?? 'Property' }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-xs text-slate-400">No image</div>
                            @endif
                        </div>
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold text-slate-950">{{ $purchase->property?->title ?? 'Property' }}</h3>
                            <p class="text-sm text-slate-600">
                                {{ $purchase->property?->listingIntentLabel() ?? 'For sale' }} - {{ $purchase->purchaseTypeLabel() }}
                            </p>
                            <p class="text-sm text-slate-600">
                                {{ $purchase->property?->city ?? 'Location' }} - {{ $purchase->property?->area ?? 'Area' }}
                            </p>
                            @if ($purchase->property?->landlord)
                                <p class="text-sm text-slate-600">Landlord: {{ $purchase->property->landlord->name }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Amount paid</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ $this->formatMoney($purchase->gross_amount, $purchase->currency) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Units</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ $purchase->units }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</p>
                            <x-status-chip tone="success">{{ str($purchase->status)->headline() }}</x-status-chip>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Confirmed</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $purchase->purchased_at?->format('M j, Y') ?? 'Recently' }}</p>
                        </div>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Next step</p>
                        <h3 class="admin-panel-title">What happens now</h3>
                        <p class="admin-panel-copy">
                            Keep this receipt for your records. VerifyHomes will reach out if any additional verification is required.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        Purchase confirmed. Your ownership record is now on file.
                    </div>
                    <a href="{{ route('tenant.payments.index') }}" class="admin-button admin-button-secondary">View payment history</a>
                </div>
            </x-admin.panel>
        </div>
    </div>
</div>
