<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TenantProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'occupation',
        'gender',
        'verification_status',
        'id_type',
        'id_number',
        'id_document_path',
        'selfie_path',
        'submitted_at',
        'verified_at',
        'verified_by',
        'rejection_reason',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'id_number' => 'encrypted',
            'submitted_at' => 'datetime',
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

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function maskedIdNumber(): ?string
    {
        if (! $this->id_number) return null;

        return str_repeat('*', max(0, strlen($this->id_number) - 4)).substr($this->id_number, -4);
    }
}
