<?php

namespace App\Contracts\Payments;

use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;

interface PaymentGatewayAdapter
{
    public function key(): string;

    public function label(): string;

    public function initiateInspectionRequestCheckout(InspectionRequest $inspectionRequest, User $payer, float $grossAmount, string $reference): array;

    public function initiateRentPaymentCheckout(Property $property, User $payer, float $grossAmount, string $reference): array;

    public function initiatePurchasePaymentCheckout(Property $property, User $payer, float $grossAmount, string $reference, string $purchaseType, int $units): array;

    public function verifyTransaction(PaymentTransaction $transaction): array;

    public function hasValidWebhookSignature(Request $request): bool;

    public function normalizeWebhookPayload(Request $request): array;
}
