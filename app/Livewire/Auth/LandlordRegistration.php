<?php

namespace App\Livewire\Auth;

use App\Models\LandlordProfile;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class LandlordRegistration extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:125'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:125', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'same:password_confirmation'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function register()
    {
        $validated = $this->validate();

        $user = DB::transaction(function () use ($validated) {
            Role::findOrCreate('landlord', 'web');

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => 'active',
            ]);

            $user->assignRole('landlord');

            LandlordProfile::create([
                'user_id' => $user->id,
                'verification_status' => 'pending',
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function render()
    {
        return view('livewire.auth.landlord-registration');
    }
}
