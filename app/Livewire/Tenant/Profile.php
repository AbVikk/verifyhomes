<?php

namespace App\Livewire\Tenant;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\TenantProfile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class Profile extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public ?string $accountPhone = null;

    public ?string $residentialAddress = null;

    public ?string $occupation = null;

    public ?string $gender = null;

    public ?string $avatarPath = null;

    public $profilePicture = null;

    public function mount(): void
    {
        $user = $this->currentUser()->fresh();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->accountPhone = $user->phone;
        $this->avatarPath = $user->avatar_path;

        if (! $this->tenantProfilesAvailable()) {
            return;
        }

        $profile = $this->tenantProfile()->fresh();

        $this->residentialAddress = $profile->address;
        $this->occupation = $profile->occupation;
        $this->gender = $profile->gender;
    }

    public function save(): void
    {
        if (! $this->tenantProfilesAvailable()) {
            session()->flash('status', 'Tenant profiles are not available yet in this environment.');

            return;
        }

        $validated = $this->validate([
            'accountPhone' => ['nullable', 'string', 'max:25'],
            'residentialAddress' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:125'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'profilePicture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $this->currentUser();
        $profile = $this->tenantProfile();
        $existingAvatarPath = $user->avatar_path;

        try {
            if ($this->profilePicture) {
                $newAvatarPath = $this->profilePicture->store("profile-pictures/{$user->id}", 'public');

                $user->forceFill([
                    'phone' => $validated['accountPhone'],
                    'avatar_path' => $newAvatarPath,
                ])->save();

                if ($existingAvatarPath) {
                    Storage::disk('public')->delete($existingAvatarPath);
                }

                $this->avatarPath = $newAvatarPath;
                $this->reset('profilePicture');
            } else {
                $user->forceFill([
                    'phone' => $validated['accountPhone'],
                ])->save();
            }
        } catch (Throwable $throwable) {
            report($throwable);
            $this->addError('profilePicture', 'We could not store your profile picture right now. Please try again.');

            return;
        }

        $profile->update([
            'address' => $validated['residentialAddress'],
            'occupation' => $validated['occupation'],
            'gender' => $validated['gender'],
        ]);

        session()->flash('status', 'Tenant profile updated successfully.');
    }

    public function removeProfilePicture(): void
    {
        $user = $this->currentUser();

        if (! $user->avatar_path) {
            return;
        }

        Storage::disk('public')->delete($user->avatar_path);

        $user->forceFill([
            'avatar_path' => null,
        ])->save();

        $this->avatarPath = null;
        $this->reset('profilePicture');

        session()->flash('status', 'Profile picture removed successfully.');
    }

    public function clearSelectedProfilePicture(): void
    {
        $this->reset('profilePicture');
    }

    public function render(): View
    {
        return view('livewire.tenant.profile', [
            'avatarUrl' => $this->avatarPath ? Storage::url($this->avatarPath) : null,
            'tenantProfilesAvailable' => $this->tenantProfilesAvailable(),
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Tenant Profile'));
    }

    protected function tenantProfilesAvailable(): bool
    {
        return Schema::hasTable('tenant_profiles');
    }

    protected function tenantProfile(): TenantProfile
    {
        $user = $this->currentUser();

        return $user->tenantProfile()->firstOrCreate([
            'user_id' => $user->getKey(),
        ]);
    }
}
