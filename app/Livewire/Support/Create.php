<?php

namespace App\Livewire\Support;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Models\MaintenanceRequest;
use App\Models\OccupancyComplaint;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\SupportRequest;
use App\Models\SupportRequestAttachment;
use App\Models\User;
use App\Support\WorkflowNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;
    use WithFileUploads;

    public string $category = 'general';
    public string $subject = '';
    public string $description = '';
    public ?int $propertyId = null;
    public ?int $inspectionRequestId = null;
    public ?int $paymentTransactionId = null;
    public ?int $maintenanceRequestId = null;
    public ?int $occupancyComplaintId = null;
    public mixed $attachment = null;

    public function updatedCategory(): void
    {
        $allowed = $this->contextFieldsForCategory();

        foreach (['propertyId', 'inspectionRequestId', 'paymentTransactionId', 'maintenanceRequestId', 'occupancyComplaintId'] as $field) {
            if (! in_array($field, $allowed, true)) {
                $this->{$field} = null;
            }
        }
    }

    public function submit(): void
    {
        $user = $this->currentUser();

        $this->validate([
            'category' => ['required', 'in:'.implode(',', $this->categoriesFor($user))],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $context = $this->authorizedContext($user);
        $attachmentMetadata = $this->attachmentMetadata();
        $storedPath = null;

        try {
            $supportRequest = DB::transaction(function () use ($context, $user, $attachmentMetadata, &$storedPath): SupportRequest {
                $supportRequest = SupportRequest::create([
                    'user_id' => $user->id,
                    'role_snapshot' => $user->isLandlord() ? 'landlord' : 'tenant',
                    'category' => $this->category,
                    'subject' => $this->subject,
                    'description' => $this->description,
                    'status' => 'open',
                    ...$context,
                ]);

                $message = $supportRequest->messages()->create([
                    'user_id' => $user->id,
                    'sender_type' => $supportRequest->role_snapshot,
                    'body' => $this->description,
                    'is_internal' => false,
                ]);

                if ($attachmentMetadata) {
                    $storedPath = $this->attachment->store('support-requests/'.$supportRequest->id, 'local');
                    SupportRequestAttachment::create([
                        'support_request_id' => $supportRequest->id,
                        'support_request_message_id' => $message->id,
                        'uploaded_by' => $user->id,
                        'original_name' => $attachmentMetadata['original_name'],
                        'file_path' => $storedPath,
                        'mime_type' => $attachmentMetadata['mime_type'],
                        'file_size' => $attachmentMetadata['file_size'],
                    ]);
                }

                return $supportRequest;
            });
        } catch (\Throwable $throwable) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $throwable;
        }

        foreach (User::role('admin')->get() as $admin) {
            $this->notifySafely($admin, 'support-request:'.$supportRequest->reference, 'New support request '.$supportRequest->reference, $user->name.' submitted a '.$supportRequest->categoryLabel().' request.', $this->supportRoute($user, 'show', $supportRequest), 'View request');
        }

        $this->notifySafely($user, 'support-request-confirmation:'.$supportRequest->reference, 'Your support request has been received', $supportRequest->reference.' - '.$supportRequest->subject, $this->supportRoute($user, 'show', $supportRequest), 'View request');

        session()->flash('status', 'Support request submitted successfully.');
        $this->redirect($this->supportRoute($user, 'show', $supportRequest), navigate: true);
    }

    public function render(): View
    {
        $user = $this->currentUser();

        return view('livewire.support.create', [
            'categories' => $this->categoriesFor($user),
            'contextFields' => $this->contextFieldsForCategory(),
            'properties' => $this->propertyQuery($user)->orderBy('title')->get(['id', 'title']),
            'inspections' => $this->inspectionQuery($user)->with('property:id,title')->latest()->get(['id', 'property_id', 'status']),
            'payments' => $this->paymentQuery($user)->latest()->get(['id', 'property_id', 'reference', 'transaction_type', 'status', 'gross_amount', 'currency']),
            'maintenanceRequests' => $this->maintenanceQuery($user)->with('property:id,title')->latest()->get(['id', 'property_id', 'status', 'title']),
            'complaints' => $this->complaintQuery($user)->with('occupancy.property:id,title')->latest()->get(['id', 'occupancy_id', 'status', 'category']),
        ])->layout('layouts.dashboard-shell', $user->isLandlord()
            ? $this->landlordShell('Submit support request')
            : $this->tenantShell('Submit support request'));
    }

    /** @return array<int, string> */
    private function categoriesFor(User $user): array
    {
        return $user->isLandlord()
            ? SupportRequest::CATEGORIES
            : array_values(array_diff(SupportRequest::CATEGORIES, ['landlord_payout']));
    }

    /** @return array<int, string> */
    private function contextFieldsForCategory(): array
    {
        return match ($this->category) {
            'inspection' => ['propertyId', 'inspectionRequestId'],
            'payment', 'rent_purchase', 'landlord_payout' => ['propertyId', 'paymentTransactionId'],
            'agreement' => ['propertyId'],
            'maintenance_moveout' => ['propertyId', 'maintenanceRequestId'],
            'complaint_dispute' => ['occupancyComplaintId'],
            'general' => ['propertyId', 'inspectionRequestId', 'paymentTransactionId', 'maintenanceRequestId', 'occupancyComplaintId'],
            default => [],
        };
    }

    /** @return array<string, int|null> */
    private function authorizedContext(User $user): array
    {
        $context = ['property_id' => null, 'inspection_request_id' => null, 'payment_transaction_id' => null, 'maintenance_request_id' => null, 'occupancy_complaint_id' => null];
        $allowed = $this->contextFieldsForCategory();

        foreach (['propertyId' => 'property_id', 'inspectionRequestId' => 'inspection_request_id', 'paymentTransactionId' => 'payment_transaction_id', 'maintenanceRequestId' => 'maintenance_request_id', 'occupancyComplaintId' => 'occupancy_complaint_id'] as $field => $column) {
            if ($this->{$field} && ! in_array($field, $allowed, true)) {
                throw ValidationException::withMessages([$field => 'This context is not available for the selected category.']);
            }
        }

        if ($this->propertyId) $context['property_id'] = $this->ownedOrConnectedId($this->propertyQuery($user), $this->propertyId, 'propertyId');
        if ($this->inspectionRequestId) $context['inspection_request_id'] = $this->ownedOrConnectedId($this->inspectionQuery($user), $this->inspectionRequestId, 'inspectionRequestId');
        if ($this->paymentTransactionId) $context['payment_transaction_id'] = $this->ownedOrConnectedId($this->paymentQuery($user), $this->paymentTransactionId, 'paymentTransactionId');
        if ($this->maintenanceRequestId) $context['maintenance_request_id'] = $this->ownedOrConnectedId($this->maintenanceQuery($user), $this->maintenanceRequestId, 'maintenanceRequestId');
        if ($this->occupancyComplaintId) $context['occupancy_complaint_id'] = $this->ownedOrConnectedId($this->complaintQuery($user), $this->occupancyComplaintId, 'occupancyComplaintId');

        return $context;
    }

    private function propertyQuery(User $user): Builder
    {
        if ($user->isLandlord()) return Property::query()->where('landlord_id', $user->id);

        return Property::query()->where(function (Builder $query) use ($user): void {
            $query->whereHas('inspectionRequests', fn (Builder $items) => $items->where('tenant_id', $user->id))
                ->orWhereHas('occupancies', fn (Builder $items) => $items->where('tenant_id', $user->id))
                ->orWhereHas('purchases', fn (Builder $items) => $items->where('buyer_id', $user->id));
        });
    }

    private function inspectionQuery(User $user): Builder
    {
        return $user->isLandlord() ? InspectionRequest::query()->forLandlord($user->id) : InspectionRequest::query()->forTenant($user->id);
    }

    private function paymentQuery(User $user): Builder
    {
        if ($user->isLandlord()) {
            return PaymentTransaction::query()->where('status', 'paid')->where('transaction_type', '!=', 'inspection_booking_fee')->whereHas('property', fn (Builder $items) => $items->where('landlord_id', $user->id));
        }

        return PaymentTransaction::query()->where('payer_id', $user->id);
    }

    private function maintenanceQuery(User $user): Builder
    {
        return MaintenanceRequest::query()->where($user->isLandlord() ? 'landlord_id' : 'tenant_id', $user->id);
    }

    private function complaintQuery(User $user): Builder
    {
        return $user->isLandlord()
            ? OccupancyComplaint::query()->whereHas('occupancy.property', fn (Builder $items) => $items->where('landlord_id', $user->id))
            : OccupancyComplaint::query()->where('tenant_id', $user->id);
    }

    private function ownedOrConnectedId(Builder $query, int $id, string $field): int
    {
        $model = $query->whereKey($id)->first();
        if (! $model) throw ValidationException::withMessages([$field => 'Choose an item available in your workspace.']);

        return (int) $model->getKey();
    }

    private function supportRoute(User $user, string $name, SupportRequest $supportRequest): string
    {
        return route(($user->isLandlord() ? 'landlord' : 'tenant').'.support.'.$name, $supportRequest);
    }

    /** @return array{original_name: string, mime_type: string, file_size: int}|null */
    private function attachmentMetadata(): ?array
    {
        if (! $this->attachment) {
            return null;
        }

        try {
            return [
                'original_name' => $this->attachment->getClientOriginalName(),
                'mime_type' => $this->attachment->getMimeType() ?? 'application/octet-stream',
                'file_size' => $this->attachment->getSize(),
            ];
        } catch (\Throwable) {
            throw ValidationException::withMessages(['attachment' => 'The selected attachment is no longer available. Choose it again.']);
        }
    }

    private function notifySafely(User $recipient, string $eventKey, string $title, string $body, string $link, string $actionLabel): void
    {
        try {
            app(WorkflowNotifier::class)->notify($recipient, $eventKey, $title, $body, $link, 'support', $actionLabel);
        } catch (\Throwable $throwable) {
            Log::warning('Support request notification could not be created after submission.', [
                'event_key' => $eventKey,
                'recipient_id' => $recipient->id,
                'exception' => $throwable::class,
            ]);
        }
    }
}
