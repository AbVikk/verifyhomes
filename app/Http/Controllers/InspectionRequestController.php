<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInspectionRequestRequest;
use App\Models\InspectionRequest;
use App\Models\InspectionRequestStatusHistory;
use App\Models\Property;
use App\Support\TermsGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class InspectionRequestController extends Controller
{
    public function store(StoreInspectionRequestRequest $request, Property $property, TermsGateService $termsGateService): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($property, $request, $validated): void {
            $inspectionRequest = InspectionRequest::create([
                'property_id' => $property->id,
                'tenant_id' => $request->user()->id,
                'status' => 'requested',
                'preferred_date' => $validated['preferred_date'] ?? null,
                'preferred_time_note' => $validated['preferred_time_note'] ?? null,
                'message' => $validated['message'] ?? null,
                'created_by_ip' => $request->ip(),
            ]);

            InspectionRequestStatusHistory::create([
                'inspection_request_id' => $inspectionRequest->id,
                'from_status' => null,
                'to_status' => 'requested',
                'changed_by' => null,
                'notes' => null,
            ]);
        });

        $termsGateService->clear('inspection-request:property:'.$property->getKey());

        return redirect()
            ->route('properties.show', $property)
            ->with('status', 'Your inspection request has been submitted.');
    }
}
