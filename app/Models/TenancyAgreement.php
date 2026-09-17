<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenancyAgreement extends Model
{
    protected $fillable = ['occupancy_id', 'tenant_id', 'landlord_id', 'property_id', 'status', 'agreement_snapshot', 'tenant_accepted_at', 'landlord_accepted_at', 'completed_at'];

    protected function casts(): array
    {
        return ['agreement_snapshot' => 'array', 'tenant_accepted_at' => 'datetime', 'landlord_accepted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $agreement): void {
            if ($agreement->getOriginal('completed_at') === null) {
                return;
            }

            if ($agreement->isDirty(['agreement_snapshot', 'tenant_accepted_at', 'landlord_accepted_at', 'completed_at', 'status'])) {
                throw new \LogicException('Completed tenancy agreements cannot be changed.');
            }
        });
    }

    public function occupancy(): BelongsTo { return $this->belongsTo(Occupancy::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(User::class, 'tenant_id'); }
    public function landlord(): BelongsTo { return $this->belongsTo(User::class, 'landlord_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
