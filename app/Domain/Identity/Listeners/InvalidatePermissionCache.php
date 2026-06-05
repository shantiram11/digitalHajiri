<?php

declare(strict_types=1);

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Services\PermissionResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listens to Spatie's RoleAttached / RoleDetached / PermissionAttached /
 * PermissionDetached events and to our own membership events to bust the
 * per-(user, org) permission cache.
 *
 * Implements ShouldQueue so the HTTP request that mutated the role/perm is
 * not blocked by cache work.
 */
final class InvalidatePermissionCache implements ShouldQueue
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Spatie fires events with `model` and `teamsId`/`team_id` keys on the
     * event objects in version 8.x. We dispatch generically off whatever
     * payload the event carries.
     */
    public function handle(object $event): void
    {
        $userId = $this->extractUserId($event);
        $orgId  = $this->extractOrgId($event);

        if ($userId === null) {
            return;
        }

        $this->resolver->flush($userId, $orgId);
    }

    private function extractUserId(object $event): ?int
    {
        if (isset($event->model) && is_object($event->model) && method_exists($event->model, 'getKey')) {
            return (int) $event->model->getKey();
        }
        if (isset($event->user_id)) {
            return (int) $event->user_id;
        }
        return null;
    }

    private function extractOrgId(object $event): ?int
    {
        foreach (['teamsId', 'team_id', 'organization_id'] as $prop) {
            if (isset($event->{$prop})) {
                return (int) $event->{$prop};
            }
        }
        return null;
    }
}
