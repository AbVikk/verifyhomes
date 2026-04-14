<?php

namespace App\Livewire\Landlord;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\LandlordProfile;
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

    public ?string $businessName = null;

    public ?string $residentialAddress = null;

    public string $city = 'Akure';

    public string $state = 'Ondo';

    public ?string $whatsappNumber = null;

    public ?string $occupationOrBusiness = null;

    public ?string $shortBioOrNotes = null;

    public ?string $bankName = null;

    public ?string $accountName = null;

    public ?string $accountNumber = null;

    public string $verificationStatus = 'pending';

    public ?string $avatarPath = null;

    public $profilePicture = null;

    public function mount(): void
    {
        $user = $this->currentUser()->fresh();
        $profile = $this->landlordProfile()->fresh();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->accountPhone = $user->phone;
        $this->businessName = $profile->business_name;
        $this->residentialAddress = $profile->address;
        $this->city = $profile->city ?: 'Akure';
        $this->state = $profile->state ?: 'Ondo';
        $this->whatsappNumber = $profile->whatsapp_number;
        $this->occupationOrBusiness = $profile->occupation_or_business;
        $this->shortBioOrNotes = $profile->short_bio_or_notes;
        $this->bankName = $profile->bank_name;
        $this->accountName = $profile->account_name;
        $this->accountNumber = $profile->account_number;
        $this->verificationStatus = $profile->verification_status;
        $this->avatarPath = $user->avatar_path;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'accountPhone' => ['nullable', 'string', 'max:25'],
            'businessName' => ['nullable', 'string', 'max:125'],
            'residentialAddress' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'whatsappNumber' => ['nullable', 'string', 'max:25'],
            'occupationOrBusiness' => ['nullable', 'string', 'max:125'],
            'shortBioOrNotes' => ['nullable', 'string'],
            'bankName' => ['nullable', 'string', 'max:150'],
            'accountName' => ['nullable', 'string', 'max:150'],
            'accountNumber' => ['nullable', 'digits_between:10,20'],
            'profilePicture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $this->currentUser();
        $profile = $this->landlordProfile();
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
            'business_name' => $validated['businessName'],
            'address' => $validated['residentialAddress'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'whatsapp_number' => $validated['whatsappNumber'],
            'occupation_or_business' => $validated['occupationOrBusiness'],
            'short_bio_or_notes' => $validated['shortBioOrNotes'],
            'bank_name' => $validated['bankName'],
            'account_name' => $validated['accountName'],
            'account_number' => $validated['accountNumber'],
        ]);

        $this->verificationStatus = $profile->fresh()->verification_status;

        session()->flash('status', 'Landlord profile updated successfully.');
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
        return view('livewire.landlord.profile', [
            'avatarUrl' => $this->avatarPath ? Storage::url($this->avatarPath) : null,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Landlord Profile'));
    }

    protected function landlordProfile(): LandlordProfile
    {
        $user = $this->currentUser();

        return $user->landlordProfile()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'verification_status' => 'pending',
                'city' => 'Akure',
                'state' => 'Ondo',
            ],
        );
    }
}
