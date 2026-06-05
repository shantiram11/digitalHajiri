<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'user_id', 'action',
        'auditable_type', 'auditable_id',
        'before', 'after', 'context', 'ip', 'user_agent',
    ];

    protected $casts = [
        'before'  => 'array',
        'after'   => 'array',
        'context' => 'array',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
