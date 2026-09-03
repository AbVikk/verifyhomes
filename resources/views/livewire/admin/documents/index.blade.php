<div class="admin-page">
    <div class="admin-page-inner">
        <x-admin.panel>
            <div class="space-y-4">
                @if (session('status'))
                    <x-admin.alert :tone="session('statusTone', 'success')">
                        {{ session('status') }}
                    </x-admin.alert>
                @endif

                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">Document review queue</h3>
                        <p class="mt-1 text-sm text-slate-600">Review uploaded private documents from landlord and property workflows in one combined queue. Pending items appear first.</p>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,0.6fr)_minmax(0,0.6fr)]">
                        <div>
                            <x-admin.label for="search">Search documents</x-admin.label>
                            <x-admin.input
                                wire:model.live.debounce.300ms="search"
                                id="search"
                                type="search"
                                placeholder="Search owner, email, filename, or document type"
                            />
                        </div>

                        <div>
                            <x-admin.label for="sourceFilter">Document group</x-admin.label>
                            <x-admin.select wire:model.live="sourceFilter" id="sourceFilter">
                                <option value="all">All documents</option>
                                <option value="landlord">Landlord documents</option>
                                <option value="property">Property documents</option>
                            </x-admin.select>
                        </div>

                        <div>
                            <x-admin.label for="statusFilter">Review state</x-admin.label>
                            <x-admin.select wire:model.live="statusFilter" id="statusFilter">
                                <option value="all">All review states</option>
                                @foreach ($reviewStatusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-admin.select>
                        </div>
                    </div>
                </div>

                @if (! $documentsAvailable)
                    <x-admin.empty-state
                        title="Document review data is not available yet in this environment."
                        copy="This page will populate automatically once landlord and property document tables are available."
                    />
                @else
                    <div class="space-y-3 md:hidden">
                        @forelse ($documents as $document)
                            <article class="admin-data-box space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900 break-words">{{ str($document->document_type)->headline() }}</p>
                                        <p class="mt-1 break-words text-sm text-slate-600">{{ $document->original_name }}</p>
                                    </div>
                                    <x-admin.badge :tone="match ($document->review_status) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }">{{ str($document->review_status ?: 'pending')->headline() }}</x-admin.badge>
                                </div>
                                <div class="text-sm text-slate-700">
                                    <p class="font-medium break-words">{{ $document->entity_label }}</p>
                                    <p class="mt-1 break-words text-slate-500">{{ $document->owner_name }}{{ $document->owner_email ? ' · '.$document->owner_email : '' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $document->source_label }} document · {{ $document->uploaded_at?->diffForHumans() ?: 'Uploaded date unavailable' }}</p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                    @if ($document->review_status === 'pending')
                                        <x-admin.button wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'approved')" wire:loading.attr="disabled" wire:target="updateDocumentStatus" variant="success" class="w-full sm:w-auto">Approve</x-admin.button>
                                        <x-admin.button wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'rejected')" wire:loading.attr="disabled" wire:target="updateDocumentStatus" variant="danger" class="w-full sm:w-auto">Reject</x-admin.button>
                                    @else
                                        <x-admin.button wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'pending')" wire:loading.attr="disabled" wire:target="updateDocumentStatus" variant="secondary" class="w-full sm:w-auto">Return to Pending</x-admin.button>
                                    @endif
                                    @if ($document->review_href)<x-admin.action-link href="{{ $document->review_href }}">Open Review</x-admin.action-link>@endif
                                    @if ($document->download_href)<x-admin.action-link href="{{ $document->download_href }}">Download</x-admin.action-link>@endif
                                </div>
                            </article>
                        @empty
                            <x-admin.empty-state title="No documents match the current search or filters." copy="Try a broader search term or adjust the source and review-state filters." />
                        @endforelse
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Source</th>
                                    <th class="admin-table-head-cell">Document</th>
                                    <th class="admin-table-head-cell">Owner / Entity</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Uploaded</th>
                                    <th class="admin-table-head-cell"></th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($documents as $document)
                                    <tr>
                                        <td class="px-4 py-4">
                                            <x-admin.badge>{{ $document->source_label }}</x-admin.badge>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ str($document->document_type)->headline() }}</p>
                                            <p class="mt-1">{{ $document->original_name }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-700">
                                            <p class="font-medium text-slate-900">{{ $document->entity_label }}</p>
                                            <p class="mt-1">{{ $document->owner_name }}</p>
                                            <p class="text-slate-500">{{ $document->owner_email ?: 'No email available' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <x-admin.badge :tone="match ($document->review_status) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }">
                                                {{ str($document->review_status ?: 'pending')->headline() }}
                                            </x-admin.badge>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            {{ $document->uploaded_at?->diffForHumans() ?: 'Not available' }}
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                @if ($document->review_status === 'pending')
                                                    <x-admin.button
                                                        wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'approved')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="updateDocumentStatus"
                                                        variant="success"
                                                        size="sm"
                                                    >
                                                        <span wire:loading.remove wire:target="updateDocumentStatus">Approve</span>
                                                        <span wire:loading wire:target="updateDocumentStatus">Updating...</span>
                                                    </x-admin.button>
                                                    <x-admin.button
                                                        wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'rejected')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="updateDocumentStatus"
                                                        variant="danger"
                                                        size="sm"
                                                    >
                                                        <span wire:loading.remove wire:target="updateDocumentStatus">Reject</span>
                                                        <span wire:loading wire:target="updateDocumentStatus">Updating...</span>
                                                    </x-admin.button>
                                                @else
                                                    <x-admin.button
                                                        wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'pending')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="updateDocumentStatus"
                                                        variant="secondary"
                                                        size="sm"
                                                    >
                                                        <span wire:loading.remove wire:target="updateDocumentStatus">Return to Pending</span>
                                                        <span wire:loading wire:target="updateDocumentStatus">Updating...</span>
                                                    </x-admin.button>

                                                    @if ($document->review_status === 'approved')
                                                        <x-admin.button
                                                            wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'rejected')"
                                                            wire:loading.attr="disabled"
                                                            wire:target="updateDocumentStatus"
                                                            variant="secondary"
                                                            size="sm"
                                                        >
                                                            <span wire:loading.remove wire:target="updateDocumentStatus">Reject</span>
                                                            <span wire:loading wire:target="updateDocumentStatus">Updating...</span>
                                                        </x-admin.button>
                                                    @else
                                                        <x-admin.button
                                                            wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'approved')"
                                                            wire:loading.attr="disabled"
                                                            wire:target="updateDocumentStatus"
                                                            variant="secondary"
                                                            size="sm"
                                                        >
                                                            <span wire:loading.remove wire:target="updateDocumentStatus">Approve</span>
                                                            <span wire:loading wire:target="updateDocumentStatus">Updating...</span>
                                                        </x-admin.button>
                                                    @endif
                                                @endif
                                                @if ($document->review_href)
                                                    <x-admin.action-link href="{{ $document->review_href }}">Open Review</x-admin.action-link>
                                                @endif
                                                @if ($document->download_href)
                                                    <x-admin.action-link href="{{ $document->download_href }}">Download</x-admin.action-link>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8">
                                            <x-admin.empty-state
                                                title="No documents match the current search or filters."
                                                copy="Try a broader search term or adjust the source and review-status filters."
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $documents->links() }}
                @endif
            </div>
        </x-admin.panel>
    </div>
</div>
