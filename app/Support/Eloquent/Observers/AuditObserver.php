<?php

declare(strict_types=1);

namespace App\Support\Eloquent\Observers;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Persists a row in audit_logs for each lifecycle event of an Auditable model.
 *
 * Stored as raw arrays for forward-compatibility — replaying a change requires
 * the exact attribute set that was modified, not a re-serialised model.
 */
final class AuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $before = [];
        $after = [];

        foreach ($model->getChanges() as $attribute => $newValue) {
            if (in_array($attribute, $model->auditExcludedAttributes(), true)) {
                continue;
            }
            $before[$attribute] = $model->getOriginal($attribute);
            $after[$attribute]  = $newValue;
        }

        if ($after === []) {
            return;
        }

        $this->record($model, 'updated', $before, $after);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getOriginal(), null);
    }

    private function record(Model $model, string $action, ?array $before, ?array $after): void
    {
        AuditLog::query()->create([
            'organization_id'  => $model->organization_id ?? null,
            'user_id'          => Auth::id(),
            'action'           => $action,
            'auditable_type'   => $model->getMorphClass(),
            'auditable_id'     => $model->getKey(),
            'before'           => $before,
            'after'            => $after,
            'ip'               => Request::ip(),
            'user_agent'       => substr((string) Request::userAgent(), 0, 500),
        ]);
    }
}
