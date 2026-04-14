<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PropertyPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'buyer_id',
        'payment_transaction_id',
        'purchase_type',
        'status',
        'units',
        'gross_amount',
        'currency',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'units' => 'integer',
            'purchased_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function purchaseTypeLabel(): string
    {
        return match ($this->purchase_type) {
            'land' => 'Land purchase',
            default => 'House purchase',
        };
    }
}
