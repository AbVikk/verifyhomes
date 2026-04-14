<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        @if (session('status'))
            <x-admin.alert>
                {{ session('status') }}
            </x-admin.alert>
        @endif

        <x-admin.panel>
            <div class="space-y-2">
                <p class="admin-eyebrow">My stays</p>
                <h2 class="admin-panel-title">Your active stays and next steps</h2>
                <p class="admin-panel-copy">Track rent timing, move-out requests, and any issues tied to your current stays.</p>
            </div>
        </x-admin.panel>

        @if ($purchasesAvailable)
            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Purchased properties</p>
                        <h3 class="admin-panel-title">Confirmed purchases</h3>
                        <p class="admin-panel-copy">Purchased listings show here once payment is verified.</p>
                    </div>

                    @if ($purchases->isEmpty())
                        <x-admin.empty-state
                            title="No purchases yet."
                            copy="When a purchase payment is confirmed, it will appear here."
                        />
                    @else
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($purchases as $purchase)
                                @php
                                    $purchaseProperty = $purchase->property;
                                    $purchaseImage = $purchaseProperty?->coverImage;
                                @endphp

                                <div class="admin-subsurface p-5">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                                            @if ($purchaseImage)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($purchaseImage->image_path) }}" alt="{{ $purchaseProperty?->title ?? 'Property' }}" class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-xs text-slate-400">No image</div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 space-y-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="text-base font-semibold text-slate-950">{{ $purchaseProperty?->title ?? 'Property' }}</h4>
                                                <span class="admin-badge admin-badge-success">Purchased</span>
                                            </div>
                                            <p class="text-sm text-slate-600">
                                                {{ $purchaseProperty?->listingIntentLabel() ?? 'For sale' }} - {{ $purchase->purchaseTypeLabel() }}
                                            </p>
                                            <p class="text-sm text-slate-600">
                                                {{ $purchaseProperty?->city ?? 'Location' }} - {{ $purchaseProperty?->area ?? 'Area' }}
                                            </p>
                                            <p class="text-sm text-slate-600">
                                                Purchase amount: {{ $this->formatMoney($purchase->gross_amount, $purchase->currency) }}
                                            </p>
                                            @if ($purchase->units > 1)
                                                <p class="text-sm text-slate-600">
                                                    Units purchased: {{ $purchase->units }}
                                                </p>
                                            @endif
                                            <p class="text-xs text-slate-500">
                                                Confirmed {{ $purchase->purchased_at?->format('M j, Y') ?? 'recently' }}
                                            </p>
                                            <a href="{{ route('tenant.purchases.show', $purchase) }}" class="admin-inline-link mt-2 inline-flex">View receipt</a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.panel>
        @endif

        @if (! $occupanciesAvailable)
            <x-admin.empty-state
                title="Occupancy tracking is not available yet."
                copy="This page will populate automatically once occupancy tracking is enabled in this environment."
            />
        @elseif ($occupancies->isEmpty())
            <x-admin.empty-state
                title="You do not have an active occupancy yet."
                copy="Once your rent payment is confirmed, your occupancy details will show here."
            />
        @else
            <div class="space-y-6">
                @foreach ($occupancies as $occupancy)
                    @php
                        $property = $occupancy->property;
                        $coverImage = $property?->coverImage;
                        $latestMoveOutRequest = $occupancy->moveOutRequests->sortByDesc('requested_at')->first();
                        $dueAt = $occupancy->computedNextPaymentDueAt();
                        $daysRemaining = $occupancy->daysUntilNextPayment();
                        $overdueDays = $occupancy->overdueDays();
                    @endphp

                    <x-admin.panel>
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
                            <div class="space-y-4">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                                        @if ($coverImage)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->image_path) }}" alt="{{ $property?->title ?? 'Property' }}" class="h-full w-full object-cover">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-xs text-slate-400">No image</div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 space-y-2">
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Stay summary</p>
                                        <h3 class="text-lg font-semibold text-slate-950">{{ $property?->title ?? 'Property' }}</h3>
                                        <p class="text-sm text-slate-600">
                                            {{ $property?->listingIntentLabel() ?? 'Rental' }} - {{ $property?->city ?? 'Location' }}
                                        </p>
                                        @if ($property?->landlord)
                                            <p class="text-sm text-slate-600">Landlord: {{ $property->landlord->name }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Payment status</p>
                                        @if (($property?->listing_intent ?? 'for_rent') !== 'for_rent')
                                            @if (($property?->listing_intent ?? 'for_rent') === 'for_sale')
                                                <p class="mt-1 text-sm font-medium text-slate-900">Purchase recorded</p>
                                                <p class="mt-1 text-sm text-slate-600">You are recorded as a buyer. Ongoing rent scheduling is not required.</p>
                                            @else
                                                <p class="mt-1 text-sm font-medium text-slate-900">Lease coordination</p>
                                                <p class="mt-1 text-sm text-slate-600">Lease activity is tracked here once the workflow is confirmed.</p>
                                            @endif
                                        @else
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $occupancy->paymentStatusLabel() }}</p>
                                            <p class="mt-1 text-sm text-slate-600">
                                                Next due date: {{ $dueAt ? $dueAt->format('M j, Y') : 'Unavailable' }}
                                            </p>
                                            @if ($overdueDays && $overdueDays > 0)
                                                <p class="mt-1 text-sm text-rose-600">Overdue by {{ $overdueDays }} day{{ $overdueDays === 1 ? '' : 's' }}.</p>
                                            @elseif ($daysRemaining !== null && $daysRemaining >= 0)
                                                <p class="mt-1 text-sm text-slate-600">{{ $daysRemaining }} day{{ $daysRemaining === 1 ? '' : 's' }} remaining.</p>
                                            @endif
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Stay status</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ str($occupancy->status)->headline() }}</p>
                                        <p class="mt-1 text-sm text-slate-600">Units held: {{ $occupancy->units }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Next actions</p>
                                    <p class="mt-2 text-sm text-slate-600">Request a move-out or report an issue for admin review and support.</p>
                                </div>

                                @if ($latestMoveOutRequest && $latestMoveOutRequest->status === 'pending')
                                    <div class="admin-alert admin-alert-warning">
                                        Move-out request submitted. Awaiting admin approval.
                                    </div>
                                @elseif ($occupancy->status === 'moved_out')
                                    <div class="admin-alert admin-alert-success">
                                        Move-out approved. This occupancy is now closed.
                                    </div>
                                @endif

                                <div class="space-y-3">
                                    <form wire:submit.prevent="submitMoveOutRequest({{ $occupancy->id }})" class="space-y-3">
                                        <label class="admin-label" for="move-out-notes-{{ $occupancy->id }}">Move-out request reason (optional)</label>
                                        <textarea
                                            id="move-out-notes-{{ $occupancy->id }}"
                                            rows="3"
                                            class="admin-control admin-control-textarea"
                                            wire:model.defer="moveOutNotes.{{ $occupancy->id }}"
                                            placeholder="Share a quick note about your planned move-out."
                                            @if ($occupancy->status === 'moved_out') disabled @endif
                                        ></textarea>

                                        <button type="submit" class="admin-button admin-button-secondary w-full" wire:loading.attr="disabled" wire:target="submitMoveOutRequest({{ $occupancy->id }})" @if ($occupancy->status === 'moved_out') disabled @endif>
                                            <span wire:loading.remove wire:target="submitMoveOutRequest({{ $occupancy->id }})">Request move-out</span>
                                            <span wire:loading wire:target="submitMoveOutRequest({{ $occupancy->id }})">Submitting...</span>
                                        </button>
                                    </form>

                                    <form wire:submit.prevent="submitComplaint({{ $occupancy->id }})" class="space-y-3">
                                        <div>
                                        <label class="admin-label" for="complaint-category-{{ $occupancy->id }}">Issue category</label>
                                            <select
                                                id="complaint-category-{{ $occupancy->id }}"
                                                class="admin-control admin-control-select"
                                                wire:model.defer="complaintCategory.{{ $occupancy->id }}"
                                                @if ($occupancy->status === 'moved_out') disabled @endif
                                            >
                                                <option value="">Select category</option>
                                                <option value="maintenance">Maintenance</option>
                                                <option value="utilities">Utilities</option>
                                                <option value="neighboring_issue">Neighboring issue</option>
                                                <option value="payment_support">Payment support</option>
                                                <option value="other">Other</option>
                                            </select>
                                            @error("complaintCategory.{$occupancy->id}") <p class="admin-error">{{ $message }}</p> @enderror
                                        </div>

                                        <div>
                                        <label class="admin-label" for="complaint-description-{{ $occupancy->id }}">Issue details</label>
                                            <textarea
                                                id="complaint-description-{{ $occupancy->id }}"
                                                rows="3"
                                                class="admin-control admin-control-textarea"
                                                wire:model.defer="complaintDescription.{{ $occupancy->id }}"
                                                placeholder="Describe the issue in a few sentences."
                                                @if ($occupancy->status === 'moved_out') disabled @endif
                                            ></textarea>
                                            @error("complaintDescription.{$occupancy->id}") <p class="admin-error">{{ $message }}</p> @enderror
                                        </div>

                                        <button type="submit" class="admin-button admin-button-primary w-full" wire:loading.attr="disabled" wire:target="submitComplaint({{ $occupancy->id }})" @if ($occupancy->status === 'moved_out') disabled @endif>
                                            <span wire:loading.remove wire:target="submitComplaint({{ $occupancy->id }})">Log complaint</span>
                                            <span wire:loading wire:target="submitComplaint({{ $occupancy->id }})">Sending...</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </x-admin.panel>
                @endforeach
            </div>
        @endif
    </div>
</div>
