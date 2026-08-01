<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * جلوگیری از دسترسی کاربران غیرادمین.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return new JsonResponse([
                'message' => 'You are not authorized to access this resource.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
