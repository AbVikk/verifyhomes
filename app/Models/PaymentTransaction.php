<?php

namespace App\Models;

use App\Support\PlatformFeeCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'payer_id',
        'property_id',
        'inspection_request_id',
        'transaction_type',
        'provider',
        'provider_reference',
        'currency',
        'status',
        'gross_amount',
        'platform_fee_percentage',
        'platform_fee_amount',
        'net_amount',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee_percentage' => 'decimal:2',
            'platform_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function inspectionRequest(): BelongsTo
    {
        return $this->belongsTo(InspectionRequest::class);
    }

    public static function buildAmounts(float|int|string $grossAmount, ?float $percentage = null): array
    {
        return PlatformFeeCalculator::breakdown($grossAmount, $percentage);
    }
}
