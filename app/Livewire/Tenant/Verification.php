<?php

namespace App\Livewire\Tenant;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\WorkflowNotifier;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class Verification extends Component
{
    use InteractsWithAuthenticatedUser, InteractsWithRoleShells, WithFileUploads;

    public string $idType = '';
    public string $idNumber = '';
    public $idDocument;
    public $selfie;
    public bool $reviewing = false;

    public function mount(): void
    {
        $profile = $this->profile();
        $this->idType = (string) ($profile->id_type ?? '');
        $this->idNumber = $profile->isVerified() ? '' : (string) ($profile->id_number ?? '');
    }

    public function submit(): void
    {
        $profile = $this->profile();
        $this->validate($this->rules());

        $base = 'tenant-verification/'.$profile->getKey();
        $idDocumentPath = $this->idDocument->store($base, 'local');
        $selfiePath = $this->selfie->store($base, 'local');
        if ($profile->id_document_path) Storage::disk('local')->delete($profile->id_document_path);
        if ($profile->selfie_path) Storage::disk('local')->delete($profile->selfie_path);

        $profile->update([
            'verification_status' => 'pending', 'id_type' => $this->idType, 'id_number' => trim($this->idNumber),
            'id_document_path' => $idDocumentPath, 'selfie_path' => $selfiePath, 'submitted_at' => now(),
            'verified_at' => null, 'verified_by' => null, 'rejection_reason' => null,
        ]);
        $notifier = app(WorkflowNotifier::class);
        $notifier->notify($this->currentUser(), 'tenant-kyc-submitted:'.$profile->id.':'.$profile->submitted_at?->timestamp, 'Identity verification submitted', 'Identity verification submitted. VerifyHomes will review your details.', route('tenant.verification'), 'verification', 'View verification');
        User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->get()->each(fn (User $admin) => $notifier->notify($admin, 'tenant-kyc-review:'.$profile->id.':'.$profile->submitted_at?->timestamp, 'Review tenant identity verification', 'Review tenant identity verification.', route('admin.tenants.show', $profile), 'verification', 'Review verification'));
        $this->reset('idDocument', 'selfie');
        $this->reviewing = false;
        session()->flash('status', 'Verification submitted. VerifyHomes is reviewing your details.');
    }

    public function review(): void
    {
        $this->validate($this->rules());
        $this->reviewing = true;
    }

    public function editDetails(): void
    {
        $this->reviewing = false;
    }

    public function render(): View
    {
        return view('livewire.tenant.verification', ['profile' => $this->profile()])
            ->layout('layouts.dashboard-shell', $this->tenantShell('Identity Verification'));
    }

    private function profile(): TenantProfile
    {
        return $this->currentUser()->tenantProfile()->firstOrCreate(['user_id' => $this->currentUserId()]);
    }

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        return [
            'idType' => ['required', 'in:nin,international_passport,voters_card'],
            'idNumber' => ['required', 'string', 'max:100'],
            'idDocument' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
