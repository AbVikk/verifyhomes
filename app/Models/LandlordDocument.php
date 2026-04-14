<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LandlordDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_profile_id',
        'document_type',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function landlordProfile(): BelongsTo
    {
        return $this->belongsTo(LandlordProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
