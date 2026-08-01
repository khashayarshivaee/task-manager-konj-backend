<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * جلوگیری از دسترسی کاربران غیرفعال.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            return new JsonResponse([
                'message' => 'Your account has been deactivated.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
