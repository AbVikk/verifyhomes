<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SupportTeamAuthController extends Controller
{
    public function login(): View { return view('support-team.login'); }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['nullable', 'boolean']]);
        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], (bool) ($credentials['remember'] ?? false))) return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        $user = $request->user();
        if (! $user->isSupportStaff() || $user->suspended_at) { Auth::logout(); return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email'); }
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        return redirect()->route($user->must_change_password ? 'support-team.password.create' : 'support-team.dashboard');
    }

    public function activation(string $token): View
    {
        $staff = $this->activationStaff($token);
        abort_unless($staff, 404);
        return view('support-team.activate', ['token' => $token, 'staff' => $staff]);
    }

    public function verifyActivation(Request $request, string $token): RedirectResponse
    {
        $staff = $this->activationStaff($token);
        if (! $staff || ! Hash::check($request->input('temporary_password', ''), $staff?->password ?? '')) return back()->withErrors(['temporary_password' => 'This activation link or temporary password is invalid.']);
        Auth::login($staff);
        $request->session()->regenerate();
        return redirect()->route('support-team.password.create');
    }

    public function password(): View { return view('support-team.change-password'); }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);
        $request->user()->update(['password' => Hash::make($validated['password']), 'must_change_password' => false, 'activated_at' => now(), 'activation_token_hash' => null, 'activation_expires_at' => null, 'last_login_at' => now()]);
        return redirect()->route('support-team.dashboard');
    }

    public function dashboard(Request $request): View { return app(SupportOperationsController::class)->dashboard($request); }

    private function activationStaff(string $token): ?User
    {
        return User::role('support_staff')->whereNull('suspended_at')->where('must_change_password', true)->where('activation_expires_at', '>', now())->get()->first(fn (User $user) => filled($user->activation_token_hash) && Hash::check($token, $user->activation_token_hash));
    }
}
