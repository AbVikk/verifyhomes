<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InspectionRequestStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_request_id',
        'from_status',
        'to_status',
        'changed_by',
        'notes',
    ];

    public function inspectionRequest(): BelongsTo
    {
        return $this->belongsTo(InspectionRequest::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
