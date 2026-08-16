<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanAccessVpnSessions
{
    private const ALLOWED_EMAIL = 'khashayarshivaee@gmail.com';

    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        if (
            $user === null ||
            strtolower((string) $user->email) !== self::ALLOWED_EMAIL
        ) {
            abort(403, 'You are not authorized to access VPN sessions.');
        }

        return $next($request);
    }
}
