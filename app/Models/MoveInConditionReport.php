<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoveInConditionReport extends Model
{
    protected $fillable = ['occupancy_id', 'landlord_id', 'tenant_id', 'property_id', 'status', 'submitted_at', 'tenant_confirmed_at', 'tenant_notes'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'tenant_confirmed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $report): void {
            if ($report->getOriginal('tenant_confirmed_at') !== null && $report->isDirty(['status', 'submitted_at', 'tenant_confirmed_at', 'tenant_notes'])) {
                throw new \LogicException('Completed move-in condition reports cannot be changed.');
            }
        });
    }

    public function occupancy(): BelongsTo { return $this->belongsTo(Occupancy::class); }
    public function landlord(): BelongsTo { return $this->belongsTo(User::class, 'landlord_id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(User::class, 'tenant_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function items(): HasMany { return $this->hasMany(MoveInConditionItem::class); }
    public function photos(): HasMany { return $this->hasMany(MoveInConditionPhoto::class); }
    public function canBeEditedByLandlord(): bool { return in_array($this->status, ['draft', 'changes_requested'], true); }
}
