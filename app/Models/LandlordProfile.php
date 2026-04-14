<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LandlordProfile extends Model
{
    use HasFactory;

    /**
     * Legacy verification summary fields remain for compatibility.
     * New verification uploads should use landlord_documents as the source of truth.
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'phone',
        'whatsapp_number',
        'occupation_or_business',
        'short_bio_or_notes',
        'bank_name',
        'account_name',
        'account_number',
        'address',
        'city',
        'state',
        'id_type',
        'id_number',
        'id_document_path',
        'selfie_path',
        'verification_status',
        'verified_at',
        'verified_by',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LandlordDocument::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LandlordStatusHistory::class)->latest();
    }

    public function canCreateProperties(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function propertyCreationBlockMessage(): string
    {
        return match ($this->verification_status) {
            'approved' => 'Your landlord verification is approved and property creation is available.',
            'suspended' => 'Your landlord verification is currently suspended. Property creation is unavailable until the admin team restores your verification status.',
            'rejected' => 'Your landlord verification was rejected. Update your profile and verification documents, then wait for a new admin review before creating a property.',
            'under_review' => 'Your landlord verification is under review. Property creation will unlock after the admin team approves your verification.',
            default => 'Your landlord verification must be approved before you can create a property. Complete your profile and upload at least one verification document to move your review forward.',
        };
    }
}
