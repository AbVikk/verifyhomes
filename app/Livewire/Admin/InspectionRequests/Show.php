<?php

namespace App\Livewire\Admin\InspectionRequests;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\InspectionRequest;
use App\Models\InspectionRequestStatusHistory;
use App\Models\PaymentTransaction;
use App\Support\AuditLogger;
use App\Support\Currency;
use App\Support\InspectionRequestOptions;
use App\Support\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use HasAdminLayout;
    use InteractsWithAuthenticatedUser;

    public ?InspectionRequest $inspectionRequest = null;

    public ?string $scheduledAt = null;

    public ?string $adminNotes = null;

    public ?string $outcomeType = null;

    public ?string $outcomeNotes = null;

    public function mount(?InspectionRequest $inspectionRequest = null, ?string $inspectionRequestId = null): void
    {
        if (! $this->detailAvailable()) {
            return;
        }

        $inspectionRequest ??= InspectionRequest::query()->find($inspectionRequestId);

        abort_if(! $inspectionRequest, 404);

        $this->inspectionRequest = $inspectionRequest;
        $this->syncFormState();
    }

    public function changeStatus(string $status): void
    {
        if (! $this->canPerformActions()) {
            return;
        }

        validator(
            [
                'status' => $status,
                'scheduledAt' => $this->scheduledAt,
                'adminNotes' => $this->adminNotes,
                'outcomeType' => $this->outcomeType,
                'outcomeNotes' => $this->outcomeNotes,
            ],
            $this->rulesForStatus($status),
            $this->validationMessages(),
        )->validate();

        $inspectionRequest = $this->inspectionRequest->fresh();
        $fromStatus = $inspectionRequest->status;

        if ($fromStatus === $status) {
            session()->flash('status', 'Inspection request already has that status.');

            return;
        }

        DB::transaction(function () use ($inspectionRequest, $fromStatus, $status): void {
            $inspectionRequest->update([
                'status' => $status,
                'scheduled_at' => InspectionRequestOptions::requiresScheduledAt($status)
                    ? $this->scheduledAt
                    : $inspectionRequest->scheduled_at,
                'admin_notes' => $this->adminNotes,
                ...$this->outcomeDataForStatus($status),
            ]);

            InspectionRequestStatusHistory::create([
                'inspection_request_id' => $inspectionRequest->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'changed_by' => $this->currentUserId(),
                'notes' => $this->adminNotes,
            ]);

            AuditLogger::log(
                action: 'inspection_request_status_changed',
                actor: $this->currentUser(),
                target: $inspectionRequest->loadMissing('property'),
                description: 'Changed inspection request status from '.str($fromStatus)->headline().' to '.str($status)->headline().'.',
                metadata: [
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'scheduled_at' => $this->scheduledAt,
                    'outcome_type' => $this->outcomeType,
                ],
            );
        });

        $this->inspectionRequest = $this->inspectionRequest->fresh();
        $this->syncFormState();

        session()->flash('status', 'Inspection request updated successfully.');
    }

    public function render(): View
    {
        $inspectionRequestAvailable = $this->hasInspectionRequestsTable() && $this->inspectionRequest !== null;
        $inspectionHistoryAvailable = $this->hasInspectionRequestStatusHistoriesTable();
        $detailAvailable = $inspectionRequestAvailable && $inspectionHistoryAvailable;
        $paymentTransactionsAvailable = $this->hasPaymentTransactionsTable();
        $latestPaymentTransaction = $paymentTransactionsAvailable && $this->inspectionRequest
            ? PaymentTransaction::query()
                ->where('inspection_request_id', $this->inspectionRequest->getKey())
                ->latest('created_at')
                ->first()
            : null;

        return $this->adminPage(view('livewire.admin.inspection-requests.show', [
            'inspectionRequest' => $detailAvailable
                ? $this->inspectionRequest->load([
                    'property.landlord',
                    'tenant',
                    'statusHistories.changedBy',
                ])
                : $this->inspectionRequest,
            'inspectionRequestAvailable' => $inspectionRequestAvailable,
            'inspectionHistoryAvailable' => $inspectionHistoryAvailable,
            'detailAvailable' => $detailAvailable,
            'paymentTransactionsAvailable' => $paymentTransactionsAvailable,
            'latestPaymentTransaction' => $latestPaymentTransaction,
            'outcomeOptions' => InspectionRequestOptions::outcomes(),
        ]), 'Inspection Request', 'Back to Inspection Requests', route('admin.inspection-requests.index'));
    }

    protected function syncFormState(): void
    {
        $this->scheduledAt = $this->inspectionRequest->scheduled_at?->format('Y-m-d\\TH:i');
        $this->adminNotes = $this->inspectionRequest->admin_notes;
        $this->outcomeType = $this->inspectionRequest->outcome_type;
        $this->outcomeNotes = $this->inspectionRequest->outcome_notes;
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    public function providerLabel(?string $provider): string
    {
        return app(PaymentGatewayManager::class)->label($provider);
    }

    public function paymentReadiness(?PaymentTransaction $paymentTransaction): string
    {
        if (! $this->hasPaymentTransactionsTable()) {
            return 'Payment data unavailable';
        }

        if (! $paymentTransaction) {
            return 'No booking fee record yet';
        }

        return match ($paymentTransaction->status) {
            'initiated' => 'Checkout started; tenant still needs to finish the provider step',
            'pending' => 'Awaiting final verification from the provider',
            'paid' => 'Booking fee verified',
            'failed' => 'Latest checkout failed',
            default => str($paymentTransaction->status)->headline()->toString(),
        };
    }

    public function paymentStatusSummary(?PaymentTransaction $paymentTransaction): string
    {
        if (! $paymentTransaction) {
            return 'No payment action yet.';
        }

        return match ($paymentTransaction->status) {
            'initiated' => 'Wait for the tenant to complete checkout before scheduling this visit.',
            'pending' => 'Do not schedule yet. The gateway return is in, but final confirmation is still pending.',
            'paid' => 'Payment is verified. Scheduling can move forward safely.',
            'failed' => 'The last checkout did not complete successfully. The tenant needs to start a new payment attempt.',
            default => 'Check the payments workspace for the latest provider update.',
        };
    }

    protected function rulesForStatus(string $status): array
    {
        return [
            'status' => ['required', Rule::in(InspectionRequestOptions::values())],
            'scheduledAt' => [
                Rule::requiredIf(InspectionRequestOptions::requiresScheduledAt($status)),
                'nullable',
                'date',
                'after_or_equal:now',
            ],
            'adminNotes' => ['nullable', 'string', 'max:2000'],
            'outcomeType' => [
                Rule::requiredIf(InspectionRequestOptions::requiresOutcomeType($status)),
                'nullable',
                Rule::in(InspectionRequestOptions::outcomeValues()),
            ],
            'outcomeNotes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function validationMessages(): array
    {
        return [
            'status.in' => 'Select a valid inspection request status.',
            'scheduledAt.required' => 'Provide a scheduled date and time before marking this request as scheduled.',
            'scheduledAt.after_or_equal' => 'Choose a scheduled date and time that is not in the past.',
            'outcomeType.required' => 'Choose an inspection outcome before marking this request as completed.',
            'outcomeType.in' => 'Select a valid inspection outcome.',
        ];
    }

    public function saveCoordinationNotes(): void
    {
        if (! $this->canPerformActions()) {
            return;
        }

        $this->validate([
            'adminNotes' => ['nullable', 'string', 'max:2000'],
            'outcomeType' => ['nullable', Rule::in(InspectionRequestOptions::outcomeValues())],
            'outcomeNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = $this->inspectionRequest->fresh()->status;

        $this->inspectionRequest->update([
            'admin_notes' => $this->adminNotes,
            ...$this->outcomeDataForStatus($status),
        ]);

        AuditLogger::log(
            action: 'inspection_request_notes_saved',
            actor: $this->currentUser(),
            target: $this->inspectionRequest->loadMissing('property'),
            description: 'Saved inspection coordination notes.',
            metadata: [
                'status' => $status,
                'outcome_type' => $this->outcomeType,
            ],
        );

        $this->inspectionRequest = $this->inspectionRequest->fresh();
        $this->syncFormState();

        session()->flash('status', 'Inspection coordination notes updated successfully.');
    }

    protected function outcomeDataForStatus(string $status): array
    {
        if (! InspectionRequestOptions::requiresOutcomeType($status)) {
            return [
                'outcome_type' => null,
                'outcome_notes' => null,
            ];
        }

        return [
            'outcome_type' => $this->outcomeType,
            'outcome_notes' => $this->outcomeNotes,
        ];
    }

    protected function detailAvailable(): bool
    {
        return $this->hasInspectionRequestsTable() && $this->hasInspectionRequestStatusHistoriesTable();
    }

    protected function canPerformActions(): bool
    {
        if ($this->detailAvailable() && $this->inspectionRequest) {
            return true;
        }

        session()->flash('status', 'Inspection request actions are not available yet in this environment.');

        return false;
    }

    protected function hasInspectionRequestsTable(): bool
    {
        return Schema::hasTable('inspection_requests');
    }

    protected function hasInspectionRequestStatusHistoriesTable(): bool
    {
        return Schema::hasTable('inspection_request_status_histories');
    }

    protected function hasPaymentTransactionsTable(): bool
    {
        return Schema::hasTable('payment_transactions');
    }
}
