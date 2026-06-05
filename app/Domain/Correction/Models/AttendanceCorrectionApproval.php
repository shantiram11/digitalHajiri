<?php

declare(strict_types=1);

namespace App\Domain\Correction\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionApproval extends Model
{
    protected $fillable = [
        'correction_id', 'step', 'approver_user_id',
        'status', 'acted_at', 'comment',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function correction(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class, 'correction_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
