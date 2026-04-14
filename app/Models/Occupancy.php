<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Occupancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'payment_transaction_id',
        'status',
        'units',
        'payment_cycle_months',
        'started_at',
        'last_payment_at',
        'next_payment_due_at',
        'last_reminder_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'units' => 'integer',
            'payment_cycle_months' => 'integer',
            'started_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'next_payment_due_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function moveOutRequests(): HasMany
    {
        return $this->hasMany(OccupancyMoveOutRequest::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(OccupancyComplaint::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'move_out_pending']);
    }

    public function paymentCycleMonths(): int
    {
        return max(1, (int) ($this->payment_cycle_months ?: 12));
    }

    public function computedNextPaymentDueAt(): ?Carbon
    {
        if ($this->next_payment_due_at) {
            return Carbon::instance($this->next_payment_due_at);
        }

        if ($this->last_payment_at) {
            return Carbon::instance($this->last_payment_at)->addMonthsNoOverflow($this->paymentCycleMonths());
        }

        if ($this->started_at) {
            return Carbon::instance($this->started_at)->addMonthsNoOverflow($this->paymentCycleMonths());
        }

        return null;
    }

    public function daysUntilNextPayment(): ?int
    {
        $due = $this->computedNextPaymentDueAt();

        if (! $due) {
            return null;
        }

        return now()->startOfDay()->diffInDays($due->startOfDay(), false);
    }

    public function overdueDays(): ?int
    {
        $days = $this->daysUntilNextPayment();

        if ($days === null) {
            return null;
        }

        return $days < 0 ? abs($days) : 0;
    }

    public function paymentStatusLabel(): string
    {
        $due = $this->computedNextPaymentDueAt();

        if (! $due) {
            return 'Next rent schedule unavailable.';
        }

        $days = $this->daysUntilNextPayment();
        $overdueDays = $this->overdueDays();

        if ($overdueDays && $overdueDays > 0) {
            return $overdueDays === 1
                ? 'Rent overdue by 1 day.'
                : "Rent overdue by {$overdueDays} days.";
        }

        if ($days === 0) {
            return 'Rent due today.';
        }

        if ($days === 1) {
            return 'Rent due in 1 day.';
        }

        return "Rent due in {$days} days.";
    }
}
