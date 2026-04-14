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
                        <p class="mt-1 text-sm text-slate-600">Review uploaded private documents from landlord and property workflows in one combined admin queue.</p>
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
                            <x-admin.label for="sourceFilter">Source type</x-admin.label>
                            <x-admin.select wire:model.live="sourceFilter" id="sourceFilter">
                                <option value="all">All sources</option>
                                <option value="landlord">Landlord</option>
                                <option value="property">Property</option>
                            </x-admin.select>
                        </div>

                        <div>
                            <x-admin.label for="statusFilter">Review status</x-admin.label>
                            <x-admin.select wire:model.live="statusFilter" id="statusFilter">
                                <option value="all">All statuses</option>
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
                    <div class="overflow-x-auto">
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
                                            <x-admin.badge>{{ str($document->review_status ?: 'pending')->headline() }}</x-admin.badge>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            {{ $document->uploaded_at?->diffForHumans() ?: 'Not available' }}
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                <x-admin.button
                                                    wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'pending')"
                                                    variant="secondary"
                                                    size="sm"
                                                >
                                                    Return to Pending
                                                </x-admin.button>
                                                <x-admin.button
                                                    wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'approved')"
                                                    variant="success"
                                                    size="sm"
                                                >
                                                    Approve
                                                </x-admin.button>
                                                <x-admin.button
                                                    wire:click="updateDocumentStatus('{{ $document->source_type }}', {{ $document->id }}, 'rejected')"
                                                    variant="danger"
                                                    size="sm"
                                                >
                                                    Reject
                                                </x-admin.button>
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
