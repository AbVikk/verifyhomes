@php
    use App\Support\LandlordOptions;
@endphp

<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <div class="admin-flash-success">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <x-admin.panel>
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Verification uploads</p>
                        <h2 class="admin-panel-title">Landlord documents</h2>
                        <p class="admin-panel-copy">
                            Documents are stored privately and prepared for admin review. Accepted file types: PDF, JPG, JPEG, PNG up to {{ $documentUploadLimitLabel }}.
                        </p>
                    </div>

                    @if (! $documentsAvailable)
                        <x-admin.empty-state
                            title="Verification document uploads are not available yet."
                            copy="This page will become available automatically after the landlord document table is available in this environment."
                        />
                    @else
                        <form wire:submit.prevent="saveDocument" class="space-y-5" data-landlord-document-root>
                            <div>
                                <label for="documentType" class="admin-label">Document type</label>
                                <select wire:model="documentType" id="documentType" class="admin-control admin-control-select">
                                    @foreach (LandlordOptions::landlordDocumentTypes() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('documentType') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="document" class="admin-label">Document file</label>
                                <input
                                    wire:key="landlord-document-input-{{ $documentInputIteration }}"
                                    wire:model="document"
                                    id="document"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    data-landlord-document-input
                                    data-max-bytes="{{ $documentUploadLimitBytes }}"
                                    data-max-label="{{ $documentUploadLimitLabel }}"
                                    class="admin-control file:mr-4 file:border-0 file:bg-transparent file:px-0 file:py-0 file:text-sm file:font-medium"
                                />
                                <p data-landlord-document-client-error class="admin-error hidden" aria-live="polite"></p>
                                <p data-landlord-document-upload-status class="admin-help hidden" aria-live="polite"></p>
                                <div wire:loading wire:target="document" class="admin-help">Preparing file...</div>
                                <div wire:loading wire:target="saveDocument" class="admin-help">Saving document...</div>
                                @if ($document)
                                    <div class="mt-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                                        Selected file: {{ $document->getClientOriginalName() }}
                                    </div>
                                @endif
                                <p class="admin-help">Current server upload limit: {{ $documentUploadLimitLabel }}. Files above this limit are rejected before upload completes.</p>
                                @error('document') <p class="admin-error">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit" wire:loading.attr="disabled" wire:target="document,saveDocument" class="admin-button admin-button-primary disabled:cursor-not-allowed disabled:opacity-60">
                                <span wire:loading.remove wire:target="document,saveDocument">Upload Document</span>
                                <span wire:loading wire:target="document,saveDocument">Uploading...</span>
                            </button>
                        </form>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel>
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Document history</p>
                        <h3 class="admin-panel-title">Uploaded documents</h3>
                        <p class="admin-panel-copy">
                            Review status is shown here, but private file paths are never exposed publicly. Legacy compatibility fields remain outside this page.
                        </p>
                    </div>

                    @if (! $documentsAvailable)
                        <x-admin.empty-state
                            title="Verification document data is not available yet."
                            copy="Document history will show here automatically once the landlord document table is available in this environment."
                        />
                    @else
                        <div class="space-y-3">
                            @forelse ($documents as $document)
                                <div class="admin-subsurface p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ str($document->document_type)->headline() }}</p>
                                            <p class="mt-1 text-sm text-slate-600">{{ $document->original_name }}</p>
                                            <p class="mt-2 text-xs text-slate-500">Uploaded {{ $document->created_at->diffForHumans() }}</p>
                                        </div>
                                        <span class="admin-badge admin-badge-neutral">
                                            {{ str($document->review_status)->headline() }}
                                        </span>
                                    </div>

                                    @if ($document->review_notes)
                                        <p class="mt-3 text-sm text-slate-600">{{ $document->review_notes }}</p>
                                    @endif
                                </div>
                            @empty
                                <x-admin.empty-state
                                    title="No verification documents uploaded yet."
                                    copy="Upload your first verification file to start building your review record."
                                />
                            @endforelse
                        </div>
                    @endif
                </div>
            </x-admin.panel>
        </div>
    </div>
</div>
