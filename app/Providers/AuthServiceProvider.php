<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Services\PermissionResolver;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tenant context must survive between resolutions within one request.
        $this->app->singleton(\App\Domain\Organization\Services\OrganizationContext::class);
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerGateBefore();
    }

    /**
     * Cross-cutting rate limits documented in docs/authentication.md §5.3.
     */
    private function registerRateLimiters(): void
    {
        // 5/min per IP AND 10/min per email — composed by hitting whichever resolves first.
        RateLimiter::for('login', function (Request $request): array {
            $email = (string) $request->input('email', '');
            return [
                Limit::perMinute(5)->by('login:ip:' . $request->ip()),
                Limit::perMinute(10)->by('login:email:' . strtolower($email)),
            ];
        });

        RateLimiter::for('forgot-password', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email', ''));
            return Limit::perMinutes(5, 3)->by('forgot:email:' . $email);
        });

        RateLimiter::for('reset-password', function (Request $request): Limit {
            return Limit::perMinutes(5, 5)->by('reset:ip:' . $request->ip());
        });

        RateLimiter::for('issue-token', function (Request $request): Limit {
            return Limit::perMinute(10)->by('issue-token:user:' . ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?? $request->ip());
        });
    }

    /**
     * Wire the PermissionResolver into Laravel's Gate so $user->can('foo')
     * consults system-role and direct-permission rules before per-record
     * policies run. See docs/authorization.md §7.4.
     */
    private function registerGateBefore(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return app(PermissionResolver::class)->allows($user, $ability) ? true : null;
        });
    }
}
