<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Invitation;

use App\Domain\Identity\Models\OrganizationInvitation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\InvitationService;
use App\Domain\Organization\Services\OrganizationContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly OrganizationContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('user.invite');
        $org = $this->context->require();

        $invitations = OrganizationInvitation::query()
            ->where('organization_id', $org->id)
            ->with(['role:id,slug,name', 'invitedBy:id,name,email'])
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json(['data' => $invitations]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('user.invite');
        $org = $this->context->require();

        $data = $request->validate([
            'email'   => ['required', 'email'],
            'role_id' => ['required', 'integer'],
        ]);

        $role = Role::query()
            ->where('organization_id', $org->id)
            ->where('id', $data['role_id'])
            ->firstOrFail();

        $invitation = $this->invitations->send($org, $data['email'], $role, $request->user());

        return response()->json(['data' => $invitation->fresh()], 201);
    }

    /**
     * Public endpoint: inspect an invitation by token (for the UI).
     */
    public function show(string $token): JsonResponse
    {
        $invitation = OrganizationInvitation::query()
            ->where('token', $token)
            ->with(['organization:id,name,slug', 'role:id,slug,name'])
            ->first();

        if ($invitation === null || ! $invitation->isPending()) {
            abort(404, 'Invitation not found or no longer valid.');
        }

        return response()->json([
            'data' => [
                'organization' => [
                    'id'   => $invitation->organization->id,
                    'name' => $invitation->organization->name,
                ],
                'role'       => $invitation->role?->name,
                'email'      => $invitation->email,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }

    /**
     * Public accept endpoint. Body: token, password?, name?
     */
    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'name'     => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:12'],
        ]);

        try {
            $membership = $this->invitations->accept(
                token: $data['token'],
                password: $data['password'] ?? null,
                name: $data['name'] ?? null,
            );
        } catch (Throwable $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'data' => [
                'organization_id' => $membership->organization_id,
                'user_id'         => $membership->user_id,
                'message'         => 'Invitation accepted. Please sign in.',
            ],
        ]);
    }

    public function revoke(Request $request, OrganizationInvitation $invitation): JsonResponse
    {
        $this->authorize('user.invite');
        $org = $this->context->require();

        if ($invitation->organization_id !== $org->id) {
            abort(404);
        }

        try {
            $this->invitations->revoke($invitation, $request->user());
        } catch (Throwable $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => ['ok' => true]]);
    }
}
