<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRentPlan extends Model
{
    use HasFactory;
    protected $fillable = ['property_id', 'period_months', 'amount', 'is_active'];
    protected function casts(): array { return ['period_months' => 'integer', 'amount' => 'decimal:2', 'is_active' => 'boolean']; }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}
