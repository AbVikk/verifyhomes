<?php

namespace App\Http\Controllers;

use App\Models\InspectionRequest;
use App\Models\Property;
use App\Support\TermsGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TermsGateController extends Controller
{
    public function open(Request $request, TermsGateService $termsGateService): JsonResponse
    {
        $gate = $this->validatedGate($request);

        $this->authorizeGate($request, $gate);

        $termsGateService->open($gate);

        return response()->json([
            'gate' => $gate,
            'seconds_required' => $termsGateService->requiredSeconds(),
            'seconds_remaining' => $termsGateService->requiredSeconds(),
        ]);
    }

    public function complete(Request $request, TermsGateService $termsGateService): JsonResponse
    {
        $gate = $this->validatedGate($request);

        $this->authorizeGate($request, $gate);

        $termsGateService->complete($gate);

        if (! $termsGateService->isCompleted($gate)) {
            return response()->json([
                'message' => 'Please read the terms before continuing.',
                'seconds_remaining' => $termsGateService->secondsRemaining($gate),
            ], 422);
        }

        return response()->json([
            'gate' => $gate,
            'seconds_remaining' => 0,
            'completed' => true,
        ]);
    }

    protected function validatedGate(Request $request): string
    {
        return (string) $request->validate([
            'gate' => ['required', 'string', 'max:180'],
        ])['gate'];
    }

    protected function authorizeGate(Request $request, string $gate): void
    {
        if (preg_match('/^inspection-request:property:(\d+)$/', $gate, $matches)) {
            abort_unless($request->user()?->isTenant(), 403);
            $property = Property::query()->findOrFail((int) $matches[1]);
            abort_unless($property->isPubliclyVisible(), 404);

            return;
        }

        if (preg_match('/^inspection-payment:request:(\d+)$/', $gate, $matches)) {
            abort_unless($request->user()?->isTenant(), 403);
            $inspectionRequest = InspectionRequest::query()->findOrFail((int) $matches[1]);
            abort_unless($inspectionRequest->tenant_id === $request->user()->id, 404);

            return;
        }

        if ($gate === 'listing-terms:create') {
            abort_unless($request->user()?->isLandlord(), 403);

            return;
        }

        if (preg_match('/^listing-terms:property:(\d+)$/', $gate, $matches)) {
            abort_unless($request->user()?->isLandlord(), 403);
            $property = Property::query()->findOrFail((int) $matches[1]);
            abort_unless($property->landlord_id === $request->user()->id, 403);

            return;
        }

        throw ValidationException::withMessages([
            'gate' => 'This terms gate is not supported.',
        ]);
    }
}
