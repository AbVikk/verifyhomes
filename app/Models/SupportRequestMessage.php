<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequestMessage extends Model
{
    protected $fillable = ['support_request_id', 'user_id', 'sender_type', 'body', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    public function supportRequest(): BelongsTo { return $this->belongsTo(SupportRequest::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
