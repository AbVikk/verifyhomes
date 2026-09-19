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

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.staff.index', [
            'members' => User::role('staff')->latest()->get(),
            'credentials' => $request->session()->pull('staff_credentials'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $password = $this->temporaryPassword();
        $member = User::create([
            ...$data,
            'password' => Hash::make($password),
            'status' => 'active',
            'invited_at' => now(),
            'must_change_password' => true,
        ]);
        $member->forceFill(['email_verified_at' => now()])->save();

        Role::findOrCreate('staff', 'web');
        $member->assignRole('staff');

        $request->session()->flash('staff_credentials', [
            'email' => $member->email,
            'password' => $password,
        ]);

        return redirect()->route('admin.staff.index')->with('status', 'Staff account created. Share the temporary password securely.');
    }

    public function suspend(User $staff): RedirectResponse
    {
        $this->staffMember($staff)->update(['suspended_at' => now(), 'status' => 'inactive']);

        return back()->with('status', 'Staff account deactivated.');
    }

    public function reactivate(User $staff): RedirectResponse
    {
        $this->staffMember($staff)->update(['suspended_at' => null, 'status' => 'active']);

        return back()->with('status', 'Staff account activated.');
    }

    public function resetAccess(User $staff): RedirectResponse
    {
        $member = $this->staffMember($staff);
        $password = $this->temporaryPassword();
        $member->update(['password' => Hash::make($password), 'invited_at' => now(), 'must_change_password' => true]);

        session()->flash('staff_credentials', [
            'email' => $member->email,
            'password' => $password,
        ]);

        return redirect()->route('admin.staff.index')->with('status', 'Staff access reset. Share the new temporary password securely.');
    }

    private function staffMember(User $user): User
    {
        abort_unless($user->hasRole('staff'), 404);

        return $user;
    }

    private function temporaryPassword(): string
    {
        return 'VH-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }
}
