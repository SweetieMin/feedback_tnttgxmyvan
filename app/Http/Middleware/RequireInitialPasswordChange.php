<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

class RequireInitialPasswordChange extends AuthenticateSession
{
    /** @param Request $request */
    public function handle($request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            $response = $next($request);

            // Bind the session to the temporary password on the login request.
            if ($request->user()?->must_change_password) {
                $this->storePasswordHashInSession($request);
            }

            return $response;
        }

        if ($user->must_change_password || $request->session()->has('password_hash_'.$this->auth->getDefaultDriver())) {
            return parent::handle($request, fn (Request $request) => $this->requireChange($request, $next));
        }

        return $next($request);
    }

    private function requireChange(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->must_change_password &&
            ! $request->routeIs('password.first.*', 'logout')
        ) {
            if ($request->expectsJson()) {
                abort(403, 'Vui lòng đổi mật khẩu tạm trước khi tiếp tục.');
            }

            return to_route('password.first.edit');
        }

        return $next($request);
    }
}
