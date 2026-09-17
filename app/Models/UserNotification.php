<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'category',
        'event_key',
        'link',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markRead(): bool
    {
        if ($this->read_at) {
            return false;
        }

        $marked = static::query()
            ->whereKey($this->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($marked) {
            $this->read_at = now();
        }

        return $marked === 1;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
