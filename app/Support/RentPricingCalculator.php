<?php

namespace App\Support;

use InvalidArgumentException;

class RentPricingCalculator
{
    public const MODEL_TENANT_PRICE = 'tenant_price';

    public const MODEL_LANDLORD_NET = 'landlord_net';

    public static function supportedModels(): array
    {
        return [
            self::MODEL_TENANT_PRICE,
            self::MODEL_LANDLORD_NET,
        ];
    }

    public static function breakdown(float|int|string $inputAmount, string $pricingModel, ?float $percentage = null): array
    {
        $inputAmount = round((float) $inputAmount, 2);
        $percentage = round($percentage ?? (float) config('payments.rent_platform_fee_percentage', 20), 2);

        if (! in_array($pricingModel, static::supportedModels(), true)) {
            throw new InvalidArgumentException("Unsupported rent pricing model [{$pricingModel}].");
        }

        if ($percentage < 0 || $percentage >= 100) {
            throw new InvalidArgumentException('Rent platform fee percentage must be between 0 and 100.');
        }

        if ($pricingModel === static::MODEL_LANDLORD_NET) {
            $displayAmount = round($inputAmount / (1 - ($percentage / 100)), 2);
            $landlordNetAmount = $inputAmount;
            $platformFeeAmount = round($displayAmount - $landlordNetAmount, 2);

            return [
                'pricing_model' => $pricingModel,
                'pricing_input_amount' => $inputAmount,
                'rent_amount' => $displayAmount,
                'landlord_net_amount' => $landlordNetAmount,
                'platform_fee_percentage' => $percentage,
                'platform_fee_amount' => $platformFeeAmount,
            ];
        }

        $displayAmount = $inputAmount;
        $platformFeeAmount = round($displayAmount * ($percentage / 100), 2);
        $landlordNetAmount = round($displayAmount - $platformFeeAmount, 2);

        return [
            'pricing_model' => $pricingModel,
            'pricing_input_amount' => $inputAmount,
            'rent_amount' => $displayAmount,
            'landlord_net_amount' => $landlordNetAmount,
            'platform_fee_percentage' => $percentage,
            'platform_fee_amount' => $platformFeeAmount,
        ];
    }
}
