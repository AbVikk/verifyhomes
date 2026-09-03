<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInspectionRequestRequest;
use App\Models\InspectionRequest;
use App\Models\InspectionRequestStatusHistory;
use App\Models\Property;
use App\Models\User;
use App\Support\TermsGateService;
use App\Support\WorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
                'schedule_response' => 'awaiting_proposal',
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

            if (Schema::hasTable('user_notifications')) {
                $notifier = app(WorkflowNotifier::class);

                User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'staff']))->get()->each(function (User $admin) use ($notifier, $inspectionRequest, $property): void {
                    $notifier->notify($admin, 'inspection-requested:'.$inspectionRequest->getKey().':admin', 'New inspection request', "A tenant requested an inspection for {$property->title}.", route('admin.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]), 'inspection_update', 'Review request');
                });
            }
        });

        $termsGateService->clear('inspection-request:property:'.$property->getKey());

        return redirect()
            ->route('properties.show', $property)
            ->with('status', 'Your inspection request has been submitted.');
    }
}
