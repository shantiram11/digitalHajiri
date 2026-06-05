<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use HasFactory, SoftDeletes, BelongsToOrganization, Auditable;

    protected $fillable = ['organization_id', 'code', 'name', 'pay_grade', 'description'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
