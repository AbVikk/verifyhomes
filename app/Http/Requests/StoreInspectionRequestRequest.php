<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Support\InspectionRequestOptions;
use App\Support\RentalEligibility;
use App\Support\TermsGateService;
use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isTenant();
    }

    public function rules(): array
    {
        return [
            'accepted_inspection_terms' => ['accepted'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_time_note' => ['nullable', 'string', 'max:125'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $property = $this->route('property');

            if (! $property instanceof Property || ! $property->isPubliclyVisible()) {
                $validator->errors()->add('property', 'This property is not available for inspection requests.');

                return;
            }

            $latestRequest = $property->inspectionRequests()
                ->where('tenant_id', $this->user()->id)
                ->latest('created_at')
                ->first();

            if ($latestRequest && ! in_array($latestRequest->status, [
                InspectionRequestOptions::STATUS_CANCELLED,
                InspectionRequestOptions::STATUS_REJECTED,
            ], true)) {
                $validator->errors()->add('property', 'You already have an inspection journey for this property. Open it to continue.');
            }

            if ($property->listing_intent === 'for_rent') {
                $eligibility = app(RentalEligibility::class)->forTenant($this->user());

                if (! $eligibility['allowed']) {
                    $validator->errors()->add('property', $eligibility['reason']);
                }
            }

            if (! app(TermsGateService::class)->isCompleted('inspection-request:property:'.$property->getKey())) {
                $validator->errors()->add('accepted_inspection_terms', 'Please read the inspection terms before continuing.');
            }
        }];
    }
}
