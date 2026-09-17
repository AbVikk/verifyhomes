<div class="admin-page">
    <div class="admin-page-inner">
        @if (! $tenantProfilesAvailable)
            <x-admin.panel>
                <x-admin.empty-state
                    title="Tenant detail data is not available yet in this environment."
                    copy="This detail page will populate automatically once the tenant profile table is available."
                />
            </x-admin.panel>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)]">
                <div class="space-y-6">
                    @if ($canReviewVerification)
                        <x-admin.panel>
                            @php($idIsImage = $tenantProfile->id_document_path && in_array(strtolower(pathinfo($tenantProfile->id_document_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true))
                            <div class="space-y-5">
                                <div><p class="admin-eyebrow">Tenant identity summary</p><h3 class="text-lg font-semibold text-slate-950">Identity verification</h3><p class="mt-1 text-sm text-slate-600">Sensitive verification images are available only to administrators.</p></div>
                                <dl class="grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">Status</dt><dd class="font-medium">{{ str($tenantProfile->verification_status)->headline() }}</dd></div><div><dt class="text-slate-500">Submitted</dt><dd>{{ $tenantProfile->submitted_at?->toFormattedDateString() ?: 'Not submitted' }}</dd></div><div><dt class="text-slate-500">ID type</dt><dd>{{ $tenantProfile->id_type ? str($tenantProfile->id_type)->headline() : 'Not submitted' }}</dd></div><div><dt class="text-slate-500">ID number</dt><dd>{{ $tenantProfile->maskedIdNumber() ?: 'Not submitted' }}</dd></div></dl>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-200 p-4"><p class="font-medium text-slate-900">ID document</p>@if ($tenantProfile->id_document_path)@if ($idIsImage)<button type="button" x-data x-on:click="$dispatch('open-modal', 'tenant-id-document')" class="mt-3 block w-full overflow-hidden rounded-xl border border-slate-200"><img src="{{ route('admin.tenants.verification.preview', [$tenantProfile, 'id']) }}" alt="Tenant ID document preview" class="max-h-56 w-full object-contain"></button>@else<div class="mt-3 rounded-xl bg-slate-100 p-5 text-sm text-slate-600">PDF document submitted. Download to review securely.</div>@endif<a href="{{ route('admin.tenants.verification.download', [$tenantProfile, 'id']) }}" class="admin-inline-link mt-3 inline-block">Download ID document</a>@else<p class="mt-3 text-sm text-slate-500">Not submitted.</p>@endif</div>
                                    <div class="rounded-2xl border border-slate-200 p-4"><p class="font-medium text-slate-900">Verification selfie</p>@if ($tenantProfile->selfie_path)<button type="button" x-data x-on:click="$dispatch('open-modal', 'tenant-verification-selfie')" class="mt-3 block w-full overflow-hidden rounded-xl border border-slate-200"><img src="{{ route('admin.tenants.verification.preview', [$tenantProfile, 'selfie']) }}" alt="Tenant verification selfie preview" class="max-h-56 w-full object-contain"></button><a href="{{ route('admin.tenants.verification.download', [$tenantProfile, 'selfie']) }}" class="admin-inline-link mt-3 inline-block">Download selfie</a>@else<p class="mt-3 text-sm text-slate-500">Not submitted.</p>@endif</div>
                                </div>

                                @if($tenantProfile->verification_status === 'pending')<form method="POST" action="{{ route('admin.tenants.verification.update', $tenantProfile) }}" class="space-y-3 border-t border-slate-200 pt-5">@csrf @method('PATCH')<label for="admin-notes" class="admin-label">Admin notes</label><textarea id="admin-notes" name="admin_notes" class="admin-control" placeholder="Internal notes (optional)"></textarea><label for="rejection-reason" class="admin-label">Request resubmission reason</label><input id="rejection-reason" name="rejection_reason" class="admin-control" placeholder="Required when requesting resubmission"><div class="flex flex-col gap-3 sm:flex-row"><button name="status" value="rejected" class="admin-button admin-button-danger">Request resubmission</button><button name="status" value="verified" class="admin-button admin-button-success">Verify tenant</button></div></form>@endif
                            </div>

                            @if ($idIsImage)<x-modal name="tenant-id-document" maxWidth="3xl"><div x-data="{ zoom: 1 }" class="bg-slate-950 p-4"><div class="mb-3 flex justify-between gap-3"><p class="text-sm font-medium text-white">ID document</p><div class="flex gap-2"><button type="button" x-on:click="zoom = Math.min(2.5, zoom + .25)" class="admin-button admin-button-secondary">Zoom in</button><button type="button" x-on:click="zoom = Math.max(1, zoom - .25)" class="admin-button admin-button-secondary">Zoom out</button><button type="button" x-on:click="zoom = 1" class="admin-button admin-button-secondary">Reset</button><button type="button" x-on:click="$dispatch('close-modal', 'tenant-id-document')" class="admin-button admin-button-secondary">Close</button></div></div><div class="max-h-[70vh] overflow-auto"><img src="{{ route('admin.tenants.verification.preview', [$tenantProfile, 'id']) }}" alt="Tenant ID document" class="mx-auto max-h-[65vh] max-w-full origin-center transition-transform" x-bind:style="`transform: scale(${zoom})`"></div></div></x-modal>@endif
                            @if ($tenantProfile->selfie_path)<x-modal name="tenant-verification-selfie" maxWidth="3xl"><div x-data="{ zoom: 1 }" class="bg-slate-950 p-4"><div class="mb-3 flex justify-between gap-3"><p class="text-sm font-medium text-white">Verification selfie</p><div class="flex gap-2"><button type="button" x-on:click="zoom = Math.min(2.5, zoom + .25)" class="admin-button admin-button-secondary">Zoom in</button><button type="button" x-on:click="zoom = Math.max(1, zoom - .25)" class="admin-button admin-button-secondary">Zoom out</button><button type="button" x-on:click="zoom = 1" class="admin-button admin-button-secondary">Reset</button><button type="button" x-on:click="$dispatch('close-modal', 'tenant-verification-selfie')" class="admin-button admin-button-secondary">Close</button></div></div><div class="max-h-[70vh] overflow-auto"><img src="{{ route('admin.tenants.verification.preview', [$tenantProfile, 'selfie']) }}" alt="Tenant verification selfie" class="mx-auto max-h-[65vh] max-w-full origin-center transition-transform" x-bind:style="`transform: scale(${zoom})`"></div></div></x-modal>@endif
                        </x-admin.panel>
                    @endif
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Account summary</h3>
                                <p class="mt-1 text-sm text-slate-600">Review the tenant account details and the profile information currently available on file.</p>
                            </div>

                            <dl class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                                <div><dt class="font-medium text-slate-500">Name</dt><dd class="mt-1">{{ $tenantProfile->user?->name }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Email</dt><dd class="mt-1">{{ $tenantProfile->user?->email }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Phone</dt><dd class="mt-1">{{ $tenantProfile->user?->phone ?: ($tenantProfile->phone ?: 'Not provided') }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Joined</dt><dd class="mt-1">{{ $tenantProfile->user?->created_at?->toFormattedDateString() ?: 'Not available' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Occupation</dt><dd class="mt-1">{{ $tenantProfile->occupation ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Gender</dt><dd class="mt-1">{{ $tenantProfile->gender ?: 'Not provided' }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium text-slate-500">Address</dt><dd class="mt-1">{{ $tenantProfile->address ?: 'Not provided' }}</dd></div>
                            </dl>
                        </div>
                    </x-admin.panel>
                </div>

                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Inspection request summary</h3>
                                <p class="mt-1 text-sm text-slate-600">Inspection activity for this tenant appears here when inspection coordination data is available.</p>
                            </div>

                            @if (! $inspectionRequestsAvailable)
                                <x-admin.empty-state
                                    title="Inspection request data is not available yet in this environment."
                                    copy="This section will populate automatically once the inspection request table is available."
                                />
                            @else
                                <div class="admin-data-box">
                                    <p class="text-sm font-medium text-slate-900">Total inspection requests</p>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $inspectionRequestCount }}</p>
                                </div>

                                <div class="space-y-3">
                                    @forelse ($inspectionRequests as $inspectionRequest)
                                        <div class="admin-data-box">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="font-medium text-slate-900">{{ $inspectionRequest->property?->title ?: 'Property unavailable' }}</p>
                                                    <p class="mt-1 text-sm text-slate-600">{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</p>
                                                </div>
                                                <x-admin.badge>{{ str($inspectionRequest->status)->headline() }}</x-admin.badge>
                                            </div>
                                            <p class="mt-2 text-sm text-slate-700">
                                                Preferred visit:
                                                {{ $inspectionRequest->preferred_date?->toFormattedDateString() ?: 'No date provided' }}
                                            </p>
                                        </div>
                                    @empty
                                        <x-admin.empty-state
                                            title="This tenant has no inspection requests yet."
                                            copy="Any inspection activity tied to this tenant will appear here automatically."
                                        />
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    </x-admin.panel>
                </div>
            </div>
        @endif
    </div>
</div>
