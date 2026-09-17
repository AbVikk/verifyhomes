<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        <div>
            <p class="admin-eyebrow">Help and support</p>
            <h2 class="admin-panel-title">Submit a support request</h2>
            <p class="admin-panel-copy">Describe what happened. You can optionally attach a JPG, PNG, WEBP, or PDF up to 5MB.</p>
        </div>

        <x-admin.panel>
            <form wire:submit="submit" class="space-y-5">
                <div>
                    <label class="admin-label" for="category">Category</label>
                    <select id="category" wire:model.live="category" class="admin-control">
                        @foreach($categories as $value)
                            <option value="{{ $value }}">{{ (new \App\Models\SupportRequest(['category' => $value]))->categoryLabel() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category')" />
                </div>

                @if(in_array('propertyId', $contextFields, true))
                    <div>
                        <label class="admin-label" for="propertyId">Property (optional)</label>
                        <select id="propertyId" wire:model="propertyId" class="admin-control"><option value="">Choose a property</option>@foreach($properties as $property)<option value="{{ $property->id }}">{{ $property->title }}</option>@endforeach</select>
                        <x-input-error :messages="$errors->get('propertyId')" />
                    </div>
                @endif

                @if(in_array('inspectionRequestId', $contextFields, true))
                    <div>
                        <label class="admin-label" for="inspectionRequestId">Inspection request (optional)</label>
                        <select id="inspectionRequestId" wire:model="inspectionRequestId" class="admin-control"><option value="">Choose an inspection</option>@foreach($inspections as $inspection)<option value="{{ $inspection->id }}">Inspection #{{ $inspection->id }} · {{ str($inspection->status)->replace('_', ' ')->headline() }}{{ $inspection->property ? ' · '.$inspection->property->title : '' }}</option>@endforeach</select>
                        <x-input-error :messages="$errors->get('inspectionRequestId')" />
                    </div>
                @endif

                @if(in_array('paymentTransactionId', $contextFields, true))
                    <div>
                        <label class="admin-label" for="paymentTransactionId">Payment (optional)</label>
                        <select id="paymentTransactionId" wire:model="paymentTransactionId" class="admin-control"><option value="">Choose a payment</option>@foreach($payments as $payment)<option value="{{ $payment->id }}">{{ $payment->reference }} · {{ \App\Support\Currency::format($payment->gross_amount, $payment->currency) }} · {{ str($payment->status)->headline() }}</option>@endforeach</select>
                        <x-input-error :messages="$errors->get('paymentTransactionId')" />
                    </div>
                @endif

                @if(in_array('maintenanceRequestId', $contextFields, true))
                    <div>
                        <label class="admin-label" for="maintenanceRequestId">Maintenance request (optional)</label>
                        <select id="maintenanceRequestId" wire:model="maintenanceRequestId" class="admin-control"><option value="">Choose a maintenance request</option>@foreach($maintenanceRequests as $maintenanceRequest)<option value="{{ $maintenanceRequest->id }}">{{ $maintenanceRequest->title }} · {{ str($maintenanceRequest->status)->replace('_', ' ')->headline() }}</option>@endforeach</select>
                        <x-input-error :messages="$errors->get('maintenanceRequestId')" />
                    </div>
                @endif

                @if(in_array('occupancyComplaintId', $contextFields, true))
                    <div>
                        <label class="admin-label" for="occupancyComplaintId">Complaint (optional)</label>
                        <select id="occupancyComplaintId" wire:model="occupancyComplaintId" class="admin-control"><option value="">Choose a complaint</option>@foreach($complaints as $complaint)<option value="{{ $complaint->id }}">Complaint #{{ $complaint->id }} · {{ str($complaint->status)->replace('_', ' ')->headline() }}{{ $complaint->occupancy?->property ? ' · '.$complaint->occupancy->property->title : '' }}</option>@endforeach</select>
                        <x-input-error :messages="$errors->get('occupancyComplaintId')" />
                    </div>
                @endif

                <div><label class="admin-label" for="subject">Subject</label><input id="subject" wire:model="subject" class="admin-control" maxlength="180"><x-input-error :messages="$errors->get('subject')" /></div>
                <div><label class="admin-label" for="description">How can we help?</label><textarea id="description" wire:model="description" class="admin-control" rows="7"></textarea><x-input-error :messages="$errors->get('description')" /></div>
                <div><label class="admin-label" for="attachment">Attachment (optional)</label><input id="attachment" wire:model="attachment" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="admin-control"><x-input-error :messages="$errors->get('attachment')" /></div>
                <button class="admin-button" wire:loading.attr="disabled" wire:target="submit,attachment" type="submit"><span wire:loading.remove wire:target="submit">Submit request</span><span wire:loading wire:target="submit">Submitting...</span></button>
            </form>
        </x-admin.panel>
    </div>
</div>
