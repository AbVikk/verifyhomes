<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportRequestAttachment extends Model
{
    protected $fillable = ['support_request_id', 'support_request_message_id', 'uploaded_by', 'original_name', 'file_path', 'mime_type', 'file_size'];

    public function scopePublic(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('support_request_message_id')
                ->orWhereHas('message', fn (Builder $message) => $message->public());
        });
    }

    public function supportRequest(): BelongsTo { return $this->belongsTo(SupportRequest::class); }
    public function message(): BelongsTo { return $this->belongsTo(SupportRequestMessage::class, 'support_request_message_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
