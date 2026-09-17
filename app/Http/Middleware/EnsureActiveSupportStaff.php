<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSupportStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->suspended_at) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('support-team.login')->withErrors(['email' => 'This support account is unavailable.']);
        }

        if ($user->must_change_password && ! $request->routeIs('support-team.password.*')) {
            return redirect()->route('support-team.password.create');
        }

        return $next($request);
    }
}
