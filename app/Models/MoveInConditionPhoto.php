<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveInConditionPhoto extends Model
{
    protected $fillable = ['move_in_condition_report_id', 'move_in_condition_item_id', 'uploaded_by_id', 'source', 'file_path', 'original_name', 'file_size'];
    protected static function booted(): void
    {
        static::creating(function (self $photo): void {
            if (MoveInConditionReport::query()->whereKey($photo->move_in_condition_report_id)->value('status') === 'completed') throw new \LogicException('Completed move-in condition evidence cannot be changed.');
        });
    }
    public function report(): BelongsTo { return $this->belongsTo(MoveInConditionReport::class, 'move_in_condition_report_id'); }
    public function item(): BelongsTo { return $this->belongsTo(MoveInConditionItem::class, 'move_in_condition_item_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by_id'); }
}
