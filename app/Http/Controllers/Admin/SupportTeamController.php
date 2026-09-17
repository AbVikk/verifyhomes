<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class SupportTeamController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.support-team.index', ['members' => User::role('support_staff')->latest()->get(), 'credentials' => $request->session()->pull('support_team_credentials')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'phone' => ['nullable', 'string', 'max:30']]);
        $member = User::create([...$data, 'password' => Hash::make($password = $this->temporaryPassword()), 'must_change_password' => true, 'invited_at' => now()]);
        Role::findOrCreate('support_staff', 'web');
        $member->assignRole('support_staff');
        $token = $this->issueActivation($member, $password);
        return redirect()->route('admin.support-team.index');
    }

    public function suspend(User $supportStaff): RedirectResponse { $this->member($supportStaff)->update(['suspended_at' => now()]); return back()->with('status', 'Support staff account suspended.'); }
    public function reactivate(User $supportStaff): RedirectResponse { $this->member($supportStaff)->update(['suspended_at' => null]); return back()->with('status', 'Support staff account reactivated.'); }
    public function reissue(User $supportStaff): RedirectResponse { $member = $this->member($supportStaff); $password = $this->temporaryPassword(); $member->update(['password' => Hash::make($password), 'must_change_password' => true, 'activated_at' => null, 'invited_at' => now()]); $this->issueActivation($member, $password); return redirect()->route('admin.support-team.index'); }

    private function issueActivation(User $member, string $password): string
    {
        $token = Str::random(64);
        $member->update(['activation_token_hash' => Hash::make($token), 'activation_expires_at' => now()->addHours(48)]);
        session()->flash('support_team_credentials', ['email' => $member->email, 'password' => $password, 'activation_url' => route('support-team.activate', $token), 'expires_at' => now()->addHours(48)->format('M j, Y g:i A')]);
        return $token;
    }

    private function member(User $user): User { abort_unless($user->hasRole('support_staff'), 404); return $user; }
    private function temporaryPassword(): string { return 'VH-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)); }
}
