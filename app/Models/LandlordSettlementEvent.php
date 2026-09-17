<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandlordSettlementEvent extends Model
{
    use HasFactory;

    protected $fillable = ['landlord_settlement_id', 'actor_id', 'event_type', 'previous_values', 'new_values', 'note', 'occurred_at'];

    protected function casts(): array { return ['previous_values' => 'array', 'new_values' => 'array', 'occurred_at' => 'datetime']; }

    public function settlement(): BelongsTo { return $this->belongsTo(LandlordSettlement::class, 'landlord_settlement_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
