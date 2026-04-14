<?php

namespace App\Http\Controllers;

use App\Support\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, PaymentWebhookService $webhookService): JsonResponse
    {
        if (! $webhookService->hasValidSignature($request)) {
            return response()->json([
                'message' => 'Invalid payment webhook signature.',
            ], 401);
        }

        $transaction = $webhookService->handleVerifiedWebhook($request);

        if (! $transaction) {
            return response()->json([
                'message' => 'Payment transaction not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Payment webhook processed successfully.',
            'reference' => $transaction->reference,
            'status' => $transaction->status,
        ]);
    }
}
