<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar_path',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function landlordProfile(): HasOne
    {
        return $this->hasOne(LandlordProfile::class);
    }

    public function tenantProfile(): HasOne
    {
        return $this->hasOne(TenantProfile::class);
    }

    public function landlordProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    public function inspectionRequests(): HasMany
    {
        return $this->hasMany(InspectionRequest::class, 'tenant_id');
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(Occupancy::class, 'tenant_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PropertyPurchase::class, 'buyer_id');
    }

    public function savedPropertyRecords(): HasMany
    {
        return $this->hasMany(SavedProperty::class, 'tenant_id');
    }

    public function savedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'saved_properties', 'tenant_id', 'property_id')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function isLandlord(): bool
    {
        return $this->hasRole('landlord');
    }

    public function isTenant(): bool
    {
        return $this->hasRole('tenant');
    }

    public function dashboardRouteName(): string
    {
        if ($this->hasAnyRole(['admin', 'staff'])) {
            return 'admin.dashboard';
        }

        if ($this->hasRole('landlord')) {
            return 'landlord.dashboard';
        }

        if ($this->hasRole('tenant')) {
            return 'tenant.dashboard';
        }

        return 'dashboard';
    }
}
