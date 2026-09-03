<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Email verification as policy, not plumbing: when
 * auth.require_email_verification is false (default) users may proceed
 * and the layout shows a reminder banner; when true this behaves exactly
 * like Laravel's "verified" middleware. Evaluated per request, so the
 * switch takes effect without re-registering routes.
 */
class VerifyEmailIfRequired
{
    public function __construct(private readonly EnsureEmailIsVerified $verified) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.require_email_verification')) {
            return $next($request);
        }

        return $this->verified->handle($request, $next);
    }
}
