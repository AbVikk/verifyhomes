<?php

namespace App\Livewire\Tenant\InspectionRequests;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\PropertyPurchase;
use App\Models\InspectionRequestStatusHistory;
use App\Models\User;
use App\Support\WorkflowNotifier;
use App\Support\Currency;
use App\Support\InspectionRequestOptions;
use App\Support\Payments\PaymentGatewayManager;
use App\Support\TermsGateService;
use App\Support\TenantVerification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public ?InspectionRequest $inspectionRequest = null;

    public ?string $scheduleResponseNotes = null;

    public function mount(?InspectionRequest $inspectionRequest = null, ?string $inspectionRequestId = null): void
    {
        if (! $this->detailAvailable()) {
            return;
        }

        $inspectionRequest ??= InspectionRequest::query()->find($inspectionRequestId);

        abort_if(! $inspectionRequest, 404);
        abort_unless($inspectionRequest->tenant_id === $this->currentUserId(), 404);

        $this->inspectionRequest = $inspectionRequest;
    }

    public function render(): View
    {
        $paymentTransactionsAvailable = $this->hasPaymentTransactionsTable();
        $inspectionRequest = $this->detailAvailable() && $this->inspectionRequest
            ? $this->inspectionRequest->load(['property'])
            : $this->inspectionRequest;
        $paymentTransactions = $paymentTransactionsAvailable && $this->inspectionRequest
            ? PaymentTransaction::query()
                ->where('payer_id', $this->currentUserId())
                ->where('inspection_request_id', $this->inspectionRequest->getKey())
                ->latest('created_at')
                ->get()
            : collect();
        $latestPaymentTransaction = $paymentTransactions->first();
        $hasPaidInspectionFee = $paymentTransactions->contains(fn (PaymentTransaction $transaction) => $transaction->status === 'paid');
        $latestRentPaymentTransaction = $paymentTransactionsAvailable && $inspectionRequest
            ? PaymentTransaction::query()->where('payer_id', $this->currentUserId())->where('property_id', $inspectionRequest->property_id)->where('transaction_type', 'rent_payment')->latest('created_at')->first()
            : null;
        $latestPurchasePaymentTransaction = $paymentTransactionsAvailable && $inspectionRequest
            ? PaymentTransaction::query()->where('payer_id', $this->currentUserId())->where('property_id', $inspectionRequest->property_id)->whereIn('transaction_type', ['house_purchase_payment', 'land_purchase_payment'])->latest('created_at')->first()
            : null;
        $purchaseReceipt = $latestPurchasePaymentTransaction?->status === 'paid' && Schema::hasTable('property_purchases')
            ? PropertyPurchase::query()->where('payment_transaction_id', $latestPurchasePaymentTransaction->getKey())->where('buyer_id', $this->currentUserId())->latest('purchased_at')->first()
            : null;

        return view('livewire.tenant.inspection-requests.show', [
            'inspectionRequest' => $inspectionRequest,
            'detailAvailable' => $this->detailAvailable() && $this->inspectionRequest !== null,
            'outcomes' => InspectionRequestOptions::outcomes(),
            'paymentTransactionsAvailable' => $paymentTransactionsAvailable,
            'paymentTransactions' => $paymentTransactions,
            'latestPaymentTransaction' => $latestPaymentTransaction,
            'hasPaidInspectionFee' => $hasPaidInspectionFee,
            'inspectionBookingFeeAmount' => (float) config('payments.transaction_amounts.inspection_booking_fee', 0),
            'latestRentPaymentTransaction' => $latestRentPaymentTransaction,
            'latestPurchasePaymentTransaction' => $latestPurchasePaymentTransaction,
            'purchaseReceipt' => $purchaseReceipt,
            'tenantIsVerified' => TenantVerification::isVerified($this->currentUser()),
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Inspection Request'));
    }

    public function acceptSchedule(): void
    {
        $this->respondToSchedule('accepted');
    }

    public function requestAnotherDate(): void
    {
        $this->validate([
            'scheduleResponseNotes' => ['required', 'string', 'max:1000'],
        ], [
            'scheduleResponseNotes.required' => 'Tell VerifyHomes what date or time would work better.',
        ]);

        $this->respondToSchedule('reschedule_requested');
    }

    public function cancelRequest(): void
    {
        if (! $this->inspectionRequest || ! in_array($this->inspectionRequest->status, [
            InspectionRequestOptions::STATUS_REQUESTED,
            InspectionRequestOptions::STATUS_SCHEDULED,
        ], true)) {
            return;
        }

        $this->changeRequestStatus(InspectionRequestOptions::STATUS_CANCELLED, 'Tenant cancelled this inspection request.');
        session()->flash('status', 'Inspection request cancelled.');
    }

    protected function respondToSchedule(string $response): void
    {
        if (! $this->inspectionRequest || ! $this->inspectionRequest->scheduleNeedsTenantResponse()) {
            session()->flash('status', 'There is no proposed inspection schedule waiting for your response.');

            return;
        }

        if ($response === 'accepted') {
            DB::transaction(function (): void {
                $request = $this->inspectionRequest->fresh();
                $request->update([
                    'schedule_response' => 'accepted',
                    'schedule_response_notes' => null,
                    'schedule_responded_at' => now(),
                ]);
                InspectionRequestStatusHistory::create([
                    'inspection_request_id' => $request->id,
                    'from_status' => $request->status,
                    'to_status' => $request->status,
                    'changed_by' => $this->currentUserId(),
                    'notes' => 'Tenant accepted the proposed inspection schedule.',
                ]);
                $this->notifyAdmins($request, 'Inspection schedule accepted', 'The tenant accepted the proposed inspection schedule.');
                app(WorkflowNotifier::class)->notify(
                    $this->currentUser(),
                    'inspection-schedule-accepted:'.$request->getKey().':'.$request->scheduled_at?->getTimestamp(),
                    'Inspection schedule accepted',
                    $request->property ? "You accepted the proposed inspection schedule for {$request->property->title}. Next: complete the inspection booking fee to confirm your booking." : 'You accepted the proposed inspection schedule. Next: complete the inspection booking fee.',
                    route('tenant.inspection-requests.show', ['inspectionRequestId' => $request->getKey()]),
                    'inspection_update',
                    'Complete booking',
                );
            });

            $this->inspectionRequest = $this->inspectionRequest->fresh();
            session()->flash('status', 'Inspection schedule accepted. Review the booking terms and pay the fee to confirm your booking.');

            return;
        }

        $this->changeRequestStatus(
            InspectionRequestOptions::STATUS_REQUESTED,
            'Tenant requested another schedule: '.$this->scheduleResponseNotes,
            [
                'schedule_response' => 'reschedule_requested',
                'schedule_response_notes' => $this->scheduleResponseNotes,
                'schedule_responded_at' => now(),
            ],
        );
        $this->scheduleResponseNotes = null;
        session()->flash('status', 'Your alternative schedule request was sent to VerifyHomes. No action is needed until a new time is proposed.');
    }

    protected function changeRequestStatus(string $status, string $historyNote, array $extra = []): void
    {
        DB::transaction(function () use ($status, $historyNote, $extra): void {
            $request = $this->inspectionRequest->fresh();
            $fromStatus = $request->status;
            $request->update(array_replace(['status' => $status], $extra));
            InspectionRequestStatusHistory::create([
                'inspection_request_id' => $request->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'changed_by' => $this->currentUserId(),
                'notes' => $historyNote,
            ]);
            $requestedAnotherDate = ($extra['schedule_response'] ?? null) === 'reschedule_requested';
            $this->notifyAdmins(
                $request,
                $requestedAnotherDate ? 'Tenant requested another inspection date' : 'Inspection schedule response',
                $requestedAnotherDate ? 'The tenant requested another date. Next: review the note and propose a new inspection schedule.' : $historyNote,
            );
        });

        $this->inspectionRequest = $this->inspectionRequest->fresh();
    }

    protected function notifyAdmins(InspectionRequest $inspectionRequest, string $title, string $body): void
    {
        if (! Schema::hasTable('user_notifications')) {
            return;
        }

        $notifier = app(WorkflowNotifier::class);

        User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'staff']))->get()->each(function (User $admin) use ($notifier, $inspectionRequest, $title, $body): void {
            $notifier->notify($admin, 'inspection-response:'.$inspectionRequest->getKey().':'.$inspectionRequest->schedule_response.':'.$inspectionRequest->schedule_responded_at?->getTimestamp(), $title, $body, route('admin.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]), 'inspection_update', 'Review request');
        });
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    public function providerLabel(?string $provider): string
    {
        return app(PaymentGatewayManager::class)->label($provider);
    }

    public function paymentStatusSummary(?string $status): string
    {
        return match ($status) {
            'initiated' => 'Checkout started. Finish the provider step to move this request forward.',
            'pending' => 'Checkout returned. VerifyHomes is waiting for final payment confirmation.',
            'paid' => 'Booking fee paid. Your inspection is booked for the scheduled time; no action is needed right now.',
            'failed' => 'Payment failed. Start a new checkout when you are ready.',
            default => 'Payment has not started yet.',
        };
    }

    public function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'initiated' => 'Checkout started',
            'pending' => 'Awaiting verification',
            'paid' => 'Payment confirmed',
            'failed' => 'Payment failed',
            default => 'Not started',
        };
    }

    public function canContinueCheckout(?PaymentTransaction $transaction): bool
    {
        return $transaction instanceof PaymentTransaction
            && in_array($transaction->status, ['initiated', 'pending'], true)
            && filled(data_get($transaction->metadata, 'checkout_url'));
    }

    public function inspectionTermsGate(): ?string
    {
        return $this->inspectionRequest
            ? 'inspection-payment:request:'.$this->inspectionRequest->getKey()
            : null;
    }

    public function inspectionTermsReady(): bool
    {
        $gate = $this->inspectionTermsGate();

        return $gate ? app(TermsGateService::class)->isCompleted($gate) : false;
    }

    public function inspectionTermsSecondsRemaining(): int
    {
        $gate = $this->inspectionTermsGate();

        return $gate ? app(TermsGateService::class)->secondsRemaining($gate) : 10;
    }

    protected function detailAvailable(): bool
    {
        return $this->hasInspectionRequestsTable() && $this->hasInspectionRequestStatusHistoriesTable();
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
