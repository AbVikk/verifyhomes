<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveOperationsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isStaff() && $user->suspended_at) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This staff account is unavailable.',
            ]);
        }

        if ($user?->isStaff() && $user->must_change_password) {
            return redirect()->route('profile.edit')->with('status', 'Set a new password before using the staff workspace.');
        }

        return $next($request);
    }
}
