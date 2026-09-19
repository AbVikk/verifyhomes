<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        if ($request->boolean('support')) {
            $request->session()->put('support_login_origin', true);
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        if ($user->isStaff()) {
            $user->update(['last_login_at' => now()]);
        }

        if ($request->session()->pull('support_login_origin', false)) {
            if ($user->isTenant()) {
                return redirect()->route('tenant.support.index');
            }

            if ($user->isLandlord()) {
                return redirect()->route('landlord.support.index');
            }
        }

        return redirect()->intended($this->redirectToDashboard($user));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $supportStaff = $request->user()?->isSupportStaff() ?? false;
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return $supportStaff ? redirect()->route('support-team.login') : redirect('/');
    }

    protected function redirectToDashboard(User $user): string
    {
        return route($user->dashboardRouteName(), absolute: false);
    }
}
