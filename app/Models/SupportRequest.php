<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportRequest extends Model
{
    use HasFactory;

    public const CATEGORIES = ['account_verification', 'inspection', 'payment', 'rent_purchase', 'landlord_payout', 'agreement', 'maintenance_moveout', 'complaint_dispute', 'general'];
    public const STATUSES = ['open', 'in_progress', 'waiting_for_user', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const ESCALATION_CATEGORIES = ['financial_action', 'landlord_payout', 'kyc_verification', 'moderation_property', 'complaint_dispute', 'policy_exception', 'other'];

    protected $fillable = ['user_id', 'role_snapshot', 'reference', 'category', 'subject', 'description', 'status', 'priority', 'assigned_to', 'assigned_at', 'property_id', 'inspection_request_id', 'payment_transaction_id', 'maintenance_request_id', 'occupancy_complaint_id', 'resolved_at', 'closed_at', 'escalated_at', 'escalated_by', 'escalation_category', 'escalation_note'];

    protected function casts(): array { return ['assigned_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'escalated_at' => 'datetime']; }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->reference ??= 'SUP-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            $request->priority ??= 'normal';
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function inspectionRequest(): BelongsTo { return $this->belongsTo(InspectionRequest::class); }
    public function paymentTransaction(): BelongsTo { return $this->belongsTo(PaymentTransaction::class); }
    public function maintenanceRequest(): BelongsTo { return $this->belongsTo(MaintenanceRequest::class); }
    public function occupancyComplaint(): BelongsTo { return $this->belongsTo(OccupancyComplaint::class); }
    public function messages(): HasMany { return $this->hasMany(SupportRequestMessage::class)->oldest(); }
    public function publicMessages(): HasMany { return $this->hasMany(SupportRequestMessage::class)->public()->oldest(); }
    public function attachments(): HasMany { return $this->hasMany(SupportRequestAttachment::class); }
    public function publicAttachments(): HasMany { return $this->hasMany(SupportRequestAttachment::class)->public(); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function escalatedBy(): BelongsTo { return $this->belongsTo(User::class, 'escalated_by'); }
    public function events(): HasMany { return $this->hasMany(SupportRequestEvent::class)->oldest('created_at'); }
    public function categoryLabel(): string { return match ($this->category) { 'account_verification' => 'Account & verification', 'inspection' => 'Property inspections', 'payment' => 'Payments & receipts', 'rent_purchase' => 'Rent & purchases', 'landlord_payout' => 'Landlord payouts', 'agreement' => 'Tenancy agreements', 'maintenance_moveout' => 'Maintenance & move-out', 'complaint_dispute' => 'Complaints & disputes', default => 'General support' }; }
}
