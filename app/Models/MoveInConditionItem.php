<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoveInConditionItem extends Model
{
    protected $fillable = ['move_in_condition_report_id', 'category', 'item_key', 'label', 'rating', 'notes'];
    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->exists && $item->report()->value('status') === 'completed') throw new \LogicException('Completed move-in condition evidence cannot be changed.');
        });
    }
    public function report(): BelongsTo { return $this->belongsTo(MoveInConditionReport::class, 'move_in_condition_report_id'); }
    public function photos(): HasMany { return $this->hasMany(MoveInConditionPhoto::class); }
}
