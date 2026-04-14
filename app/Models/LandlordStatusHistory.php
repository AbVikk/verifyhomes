<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LandlordStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_profile_id',
        'from_status',
        'to_status',
        'changed_by',
        'notes',
    ];

    public function landlordProfile(): BelongsTo
    {
        return $this->belongsTo(LandlordProfile::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
