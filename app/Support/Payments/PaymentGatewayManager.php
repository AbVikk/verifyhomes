<?php

namespace App\Support\Payments;

use App\Contracts\Payments\PaymentGatewayAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function default(): PaymentGatewayAdapter
    {
        return $this->for((string) config('payments.default_provider', 'stub'));
    }

    public function for(?string $provider): PaymentGatewayAdapter
    {
        return match ($provider ?: 'stub') {
            'stub' => app(StubPaymentGatewayAdapter::class),
            'paystack' => app(PaystackPaymentGatewayAdapter::class),
            default => throw new InvalidArgumentException("Unsupported payment provider [{$provider}]."),
        };
    }

    public function resolveWebhookAdapter(Request $request): ?PaymentGatewayAdapter
    {
        $providers = collect(array_keys((array) config('payments.providers', [])))
            ->prepend((string) config('payments.default_provider', 'stub'))
            ->filter()
            ->unique()
            ->values();

        foreach ($providers as $provider) {
            try {
                $adapter = $this->for($provider);
            } catch (InvalidArgumentException) {
                continue;
            }

            if ($adapter->hasValidWebhookSignature($request)) {
                return $adapter;
            }
        }

        return null;
    }

    public function label(?string $provider): string
    {
        if (blank($provider)) {
            return 'No provider assigned';
        }

        try {
            return $this->for($provider)->label();
        } catch (InvalidArgumentException) {
            return Str::of($provider)->replace(['_', '-'], ' ')->headline()->toString();
        }
    }
}
