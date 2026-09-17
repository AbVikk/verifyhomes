<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequestEvent extends Model
{
    use HasFactory;

    public const TYPES = ['assigned', 'reassigned', 'unassigned', 'claimed', 'priority_changed', 'status_changed', 'public_reply_sent', 'internal_note_added', 'escalated_to_admin', 'escalation_cleared'];

    public $timestamps = false;

    protected $fillable = ['support_request_id', 'actor_id', 'event_type', 'previous_value', 'new_value', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
