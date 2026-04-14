<div class="admin-page">
    <div class="admin-page-inner">
            @if (session('status'))
                <x-admin.alert>
                    {{ session('status') }}
                </x-admin.alert>
            @endif

            @if (! $historyAvailable)
                <x-admin.empty-state
                    title="Landlord review detail is not fully available yet."
                    copy="This page will show landlord review history and actions again once landlord history data is available in this environment."
                />
            @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)]">
                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Account summary</h3>
                                <p class="mt-1 text-sm text-slate-600">Review the landlord account and submitted verification details.</p>
                            </div>

                            <dl class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                                <div><dt class="font-medium text-slate-500">Name</dt><dd class="mt-1">{{ $landlordProfile->user?->name }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Email</dt><dd class="mt-1">{{ $landlordProfile->user?->email }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Phone</dt><dd class="mt-1">{{ $landlordProfile->user?->phone ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">WhatsApp</dt><dd class="mt-1">{{ $landlordProfile->whatsapp_number ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Display name</dt><dd class="mt-1">{{ $landlordProfile->business_name ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Occupation or business activity</dt><dd class="mt-1">{{ $landlordProfile->occupation_or_business ?: 'Not provided' }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium text-slate-500">Residential address</dt><dd class="mt-1">{{ $landlordProfile->address ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">City</dt><dd class="mt-1">{{ $landlordProfile->city }}</dd></div>
                                <div><dt class="font-medium text-slate-500">State</dt><dd class="mt-1">{{ $landlordProfile->state }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium text-slate-500">Short notes</dt><dd class="mt-1">{{ $landlordProfile->short_bio_or_notes ?: 'No notes provided' }}</dd></div>
                            </dl>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Verification documents</h3>
                                <p class="mt-1 text-sm text-slate-600">Use the document list below as the source for review.</p>
                            </div>

                            <div class="space-y-3">
                                @forelse ($landlordProfile->documents as $document)
                                    <div class="admin-data-box flex items-start justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ str($document->document_type)->headline() }}</p>
                                            <p class="text-sm text-slate-600">{{ $document->original_name }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Status: {{ str($document->review_status)->headline() }}</p>
                                        </div>
                                        <x-admin.button tag="a" variant="secondary" size="sm" href="{{ route('admin.landlords.documents.download', [$landlordProfile, $document]) }}">
                                            Download
                                        </x-admin.button>
                                    </div>
                                @empty
                                    <x-admin.empty-state title="No landlord verification documents uploaded yet." />
                                @endforelse
                            </div>
                        </div>
                    </x-admin.panel>
                </div>

                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Verification status</h3>
                                <p class="mt-1 text-sm text-slate-600">Current status: {{ str($landlordProfile->verification_status)->headline() }}</p>
                            </div>

                            <div>
                                <x-admin.label for="reviewNotes">Review notes</x-admin.label>
                                <x-admin.textarea wire:model.defer="reviewNotes" id="reviewNotes" rows="4" />
                                <x-admin.error for="reviewNotes" />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <x-admin.button wire:click="changeStatus('under_review')" variant="secondary">Mark Under Review</x-admin.button>
                                <x-admin.button wire:click="changeStatus('approved')" variant="success">Approve</x-admin.button>
                                <x-admin.button wire:click="changeStatus('rejected')" variant="danger">Reject</x-admin.button>
                                <x-admin.button wire:click="changeStatus('suspended')" variant="warning">Suspend</x-admin.button>
                            </div>
                        </div>
                    </x-admin.panel>

                    <x-admin.partials.status-history-card
                        title="Status history"
                        description="Every admin or staff review change is recorded here."
                        :histories="$landlordProfile->statusHistories"
                    />
                </div>
            </div>
            @endif
    </div>
</div>
