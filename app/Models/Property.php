<?php

namespace App\Models;

use App\Support\Currency;
use App\Support\PublicPropertyVisibility;
use App\Support\RentPricingCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_id',
        'title',
        'slug',
        'description',
        'listing_intent',
        'pricing_model',
        'property_type',
        'land_size',
        'land_size_unit',
        'rent_amount',
        'pricing_input_amount',
        'landlord_net_amount',
        'platform_fee_percentage',
        'caution_fee',
        'service_charge',
        'total_units',
        'occupied_units',
        'bedrooms',
        'bathrooms',
        'toilets',
        'state',
        'lga',
        'city',
        'area',
        'street',
        'landmark',
        'address_text',
        'youtube_url',
        'is_verified',
        'is_published',
        'status',
        'verified_at',
        'verified_by',
        'physically_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'land_size' => 'decimal:2',
            'rent_amount' => 'decimal:2',
            'pricing_input_amount' => 'decimal:2',
            'landlord_net_amount' => 'decimal:2',
            'platform_fee_percentage' => 'decimal:2',
            'caution_fee' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'total_units' => 'integer',
            'occupied_units' => 'integer',
            'is_verified' => 'boolean',
            'is_published' => 'boolean',
            'verified_at' => 'datetime',
            'physically_verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Property $property) {
            if (blank($property->slug)) {
                $property->slug = static::generateUniqueSlug($property->title);
            }
        });
    }

    protected static function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return PublicPropertyVisibility::apply($query);
    }

    public function isPubliclyVisible(): bool
    {
        return PublicPropertyVisibility::matches($this);
    }

    public function getAvailableUnitsAttribute(): int
    {
        return max(0, (int) $this->total_units - (int) $this->occupied_units);
    }

    public function isFullyOccupied(): bool
    {
        return $this->available_units <= 0;
    }

    public function availabilityLabel(): string
    {
        if ($this->isFullyOccupied()) {
            return 'Fully occupied';
        }

        if ($this->available_units === 1) {
            return '1 unit left';
        }

        return $this->available_units.' units available';
    }

    public function availabilityDetail(): string
    {
        return $this->available_units.' available of '.$this->total_units.' total unit'.($this->total_units === 1 ? '' : 's');
    }

    public function listingIntentLabel(): string
    {
        return match ($this->listing_intent) {
            'for_sale' => 'For Sale',
            'for_lease' => 'For Lease',
            default => 'For Rent',
        };
    }

    public function primaryPriceLabel(): string
    {
        return match ($this->listing_intent) {
            'for_sale' => 'Sale price',
            'for_lease' => 'Lease amount',
            default => 'Rent amount',
        };
    }

    public function formattedPrimaryPrice(): string
    {
        return Currency::format($this->rent_amount);
    }

    public function landSizeLabel(): ?string
    {
        if ($this->land_size === null || blank($this->land_size_unit)) {
            return null;
        }

        $size = number_format((float) $this->land_size, 2, '.', ',');
        $size = str_ends_with($size, '.00') ? substr($size, 0, -3) : $size;

        return trim($size.' '.$this->land_size_unit);
    }

    public function pricingModelLabel(): string
    {
        return match ($this->pricing_model) {
            RentPricingCalculator::MODEL_LANDLORD_NET => 'Landlord target net',
            default => 'Tenant-facing listed rent',
        };
    }

    public function pricingModelSummary(): string
    {
        if ($this->listing_intent !== 'for_rent') {
            return 'Platform fee pricing only applies to successful completed rent payments. This listing keeps the entered amount as its primary price.';
        }

        return match ($this->pricing_model) {
            RentPricingCalculator::MODEL_LANDLORD_NET => 'The entered amount is the landlord target net. The public rent is grossed up so the 20% platform fee is folded into the listed rent.',
            default => 'The entered amount is the tenant-facing listed rent. The landlord net is calculated by deducting the 20% platform fee from successful completed rent payments.',
        };
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(PropertyImage::class)->where('is_cover', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PropertyDocument::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PropertyStatusHistory::class)->latest();
    }

    public function inspectionRequests(): HasMany
    {
        return $this->hasMany(InspectionRequest::class)->latest();
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(Occupancy::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PropertyPurchase::class);
    }

    public function savedByTenants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_properties', 'property_id', 'tenant_id')
            ->withTimestamps();
    }
}
