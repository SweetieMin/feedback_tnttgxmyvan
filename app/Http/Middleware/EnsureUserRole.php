<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_if($request->user() === null, 401);

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless(in_array($request->user()->role, $allowed, true), 403);

        return $next($request);
    }
}
