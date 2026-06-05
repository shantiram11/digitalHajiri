<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'contact_email', 'contact_phone', 'address',
        'timezone', 'fiscal_year_start', 'settings', 'status',
    ];

    protected $casts = [
        'settings'          => 'array',
        'fiscal_year_start' => 'date',
    ];

    /**
     * Users whose DEFAULT organization is this one. NOT the authority on
     * who can access this organization — use `members()` for that.
     */
    public function defaultUsers(): HasMany
    {
        return $this->hasMany(\App\Models\User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'organization_user')
            ->withPivot(['status', 'joined_at', 'invited_by_user_id', 'default_role_id'])
            ->withTimestamps();
    }

    public function activeMembers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(\App\Domain\Employee\Models\Employee::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(\App\Domain\Employee\Models\Department::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(\App\Domain\Device\Models\Device::class);
    }
}
