<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandlordSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id', 'landlord_id', 'property_id', 'gross_amount_snapshot',
        'platform_fee_snapshot', 'landlord_entitlement_snapshot', 'payout_amount',
        'currency', 'status', 'payout_reference', 'payout_method', 'note', 'bank_name',
        'account_name', 'masked_account_number', 'recorded_by', 'recorded_at', 'reversed_at', 'reversed_by',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount_snapshot' => 'decimal:2', 'platform_fee_snapshot' => 'decimal:2',
            'landlord_entitlement_snapshot' => 'decimal:2', 'payout_amount' => 'decimal:2',
            'recorded_at' => 'datetime', 'reversed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo { return $this->belongsTo(PaymentTransaction::class, 'transaction_id'); }
    public function landlord(): BelongsTo { return $this->belongsTo(User::class, 'landlord_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function reverser(): BelongsTo { return $this->belongsTo(User::class, 'reversed_by'); }
    public function events(): HasMany { return $this->hasMany(LandlordSettlementEvent::class)->orderBy('occurred_at'); }
}
