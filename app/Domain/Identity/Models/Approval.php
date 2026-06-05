<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = [
        'organization_id', 'workflow_id', 'approvable_type', 'approvable_id',
        'step', 'approver_user_id', 'status', 'acted_at', 'comment',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
