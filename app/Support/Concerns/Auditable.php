<?php

declare(strict_types=1);

namespace App\Support\Concerns;

/**
 * Marker trait. Models tagged with this are audited by the AuditObserver
 * registered in AppServiceProvider::boot(). Registration happens centrally
 * to avoid Eloquent's "model boot recursion" trap when observers are
 * registered inside `bootSomething()` hooks.
 */
trait Auditable
{
    /**
     * Override on the model to exclude noisy fields (e.g. updated_at, raw_payload).
     *
     * @return array<int, string>
     */
    public function auditExcludedAttributes(): array
    {
        return ['updated_at'];
    }
}
