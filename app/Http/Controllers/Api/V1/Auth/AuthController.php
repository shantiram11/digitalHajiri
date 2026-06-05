<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Organization\Models\OrganizationMembership;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    /**
     * SPA login. Uses the session guard via Sanctum's stateful middleware.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->enforceLoginRateLimit($request);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            $this->recordFailedAttempt($user, $request);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActiveAccount()) {
            throw ValidationException::withMessages([
                'email' => $user->isLocked()
                    ? __('Account is temporarily locked. Please try again later.')
                    : __('Account is suspended.'),
            ]);
        }

        // Session login (SPA cookie flow). hasSession() is false when the
        // request is made without the stateful Sanctum middleware (e.g. tests
        // that POST directly to the API without going through the SPA shell).
        Auth::guard('web')->login($user, remember: false);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill([
            'last_login_at'         => now(),
            'last_login_ip'         => $request->ip(),
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ])->save();

        RateLimiter::clear($this->throttleKey($request, 'login'));

        return response()->json(['data' => $this->meResponse($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Bearer token path. Skip the TransientToken that Sanctum returns for
        // session-authenticated requests — it has no row to delete.
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        // Session path
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->meResponse($request)]);
    }

    /**
     * Set the active org in the session. Validated to be one of the user's
     * active memberships.
     */
    public function selectOrganization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer'],
        ]);

        $isMember = $request->user()->memberships()
            ->where('organization_id', $data['organization_id'])
            ->where('status', 'active')
            ->exists();

        if (! $isMember && ! $request->user()->isSuperAdmin()) {
            abort(409, 'You are not a member of that organization.');
        }

        $request->session()->put('current_organization_id', (int) $data['organization_id']);

        return response()->json(['data' => ['ok' => true, 'organization_id' => (int) $data['organization_id']]]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($data); // always returns success-shape; we never echo state.
        return response()->json(['data' => ['ok' => true]]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'token'    => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()->mixedCase()->symbols()],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password'             => Hash::make($password),
                'password_changed_at'  => now(),
                'force_password_change'=> false,
            ])->save();
            // Revoke all sessions + tokens.
            $user->tokens()->delete();
        });

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()->mixedCase()->symbols()],
        ]);

        $request->user()->forceFill([
            'password'              => Hash::make($data['password']),
            'password_changed_at'   => now(),
            'force_password_change' => false,
        ])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Issue a bearer token (for mobile pairing or integrations).
     * Requires an authenticated session — proves the user is at a trusted
     * surface before we mint a long-lived credential.
     */
    public function issueToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_name' => ['required', 'string', 'max:120'],
            'abilities'   => ['array'],
            'abilities.*' => ['string'],
        ]);

        $token = $request->user()->createToken($data['device_name'], $data['abilities'] ?? ['*']);

        return response()->json([
            'data' => [
                'token'     => $token->plainTextToken,
                'token_id'  => $token->accessToken->id,
                'abilities' => $token->accessToken->abilities,
            ],
        ]);
    }

    public function listTokens(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->tokens()->latest('id')->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']),
        ]);
    }

    public function revokeToken(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->where('id', $id)->delete();
        return response()->json(['data' => ['ok' => true]]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function enforceLoginRateLimit(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($request, 'login'), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($request, 'login'));
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
            ]);
        }
        RateLimiter::hit($this->throttleKey($request, 'login'), 60);
    }

    private function recordFailedAttempt(?User $user, Request $request): void
    {
        if ($user === null) {
            return;
        }
        $attempts = $user->failed_login_attempts + 1;
        $update = ['failed_login_attempts' => $attempts];
        if ($attempts >= 10) {
            $update['locked_until'] = now()->addMinutes(30);
            $update['failed_login_attempts'] = 0;
        }
        $user->forceFill($update)->save();
    }

    private function throttleKey(Request $request, string $action): string
    {
        return $action . ':' . strtolower((string) $request->input('email')) . ':' . $request->ip();
    }

    /** @return array<string, mixed> */
    private function meResponse(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return [];
        }

        $memberships = $user->memberships()
            ->with(['organization:id,name,slug', 'defaultRole:id,slug,name'])
            ->where('status', 'active')
            ->get()
            ->map(fn (OrganizationMembership $m) => [
                'organization_id'   => $m->organization_id,
                'organization_name' => $m->organization?->name,
                'organization_slug' => $m->organization?->slug,
                'default_role'      => $m->defaultRole?->slug,
                'status'            => $m->status,
            ]);

        $activeOrgId = $request->hasSession()
            ? ($request->session()->get('current_organization_id') ?? $user->organization_id)
            : $user->organization_id;

        return [
            'user' => [
                'id'                    => $user->id,
                'name'                  => $user->name,
                'email'                 => $user->email,
                'email_verified_at'     => $user->email_verified_at,
                'organization_id'       => $user->organization_id,
                'force_password_change' => (bool) $user->force_password_change,
            ],
            'memberships'              => $memberships,
            'system_roles'             => $user->roles()->whereNull('roles.organization_id')->pluck('roles.slug'),
            'active_organization_id'   => $activeOrgId,
            'is_super_admin'           => $user->isSuperAdmin(),
        ];
    }
}
