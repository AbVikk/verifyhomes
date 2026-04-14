<?php

namespace App\Models;

use App\Support\InspectionRequestOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InspectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'status',
        'preferred_date',
        'preferred_time_note',
        'message',
        'landlord_note',
        'admin_notes',
        'outcome_type',
        'outcome_notes',
        'scheduled_at',
        'created_by_ip',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'scheduled_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', InspectionRequestOptions::openStatuses());
    }

    public function scopeForLandlord(Builder $query, int $landlordId): Builder
    {
        return $query->whereHas('property', fn (Builder $propertyQuery) => $propertyQuery->where('landlord_id', $landlordId));
    }

    public function scopeOrderedForCoordination(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE status WHEN 'requested' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'rejected' THEN 2 WHEN 'cancelled' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END")
            ->latest();
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InspectionRequestStatusHistory::class)->latest();
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest();
    }

    public function showsOutcome(): bool
    {
        return InspectionRequestOptions::isCompleted($this->status);
    }

    public function outcomeLabel(): ?string
    {
        if (blank($this->outcome_type)) {
            return null;
        }

        return InspectionRequestOptions::outcomes()[$this->outcome_type] ?? null;
    }

    public function hasOutcomeNotes(): bool
    {
        return filled($this->outcome_notes);
    }
}
