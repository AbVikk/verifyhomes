<?php

namespace App\Livewire\Landlord;

use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\LandlordDocument;
use App\Models\LandlordProfile;
use App\Models\User;
use App\Support\LandlordOptions;
use App\Support\UploadConfiguration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class Documents extends Component
{
    use InteractsWithRoleShells;
    use WithFileUploads;

    public string $documentType = 'national_id';

    public int $documentInputIteration = 0;

    public $document;

    public function mount(): void
    {
        UploadConfiguration::ensureLivewireTemporaryUploadDirectoryExists();
    }

    public function saveDocument(): void
    {
        if (! $this->hasLandlordDocumentsTable()) {
            session()->flash('status', 'Verification document uploads are not available yet in this environment.');

            return;
        }

        $this->resetErrorBag(['document', 'documentType']);

        $documentUploadLimitKilobytes = $this->documentUploadLimitKilobytes();

        $validated = $this->validate([
            'documentType' => ['required', 'string', 'max:50', Rule::in(LandlordOptions::landlordDocumentTypeValues())],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.$documentUploadLimitKilobytes],
        ], [
            'documentType.in' => 'Select a valid landlord verification document type.',
            'document.max' => 'The selected document exceeds the current server upload limit of '.UploadConfiguration::formatKilobytes($documentUploadLimitKilobytes).'.',
        ]);

        $profile = $this->landlordProfile();
        $uploadedFile = $validated['document'];

        try {
            $this->storeVerificationDocument($profile, $validated['documentType'], $uploadedFile);
        } catch (Throwable $throwable) {
            report($throwable);
            $this->addError('document', 'We could not store this verification document right now. Please try again.');

            return;
        }

        $this->reset('document');
        $this->documentType = 'national_id';
        $this->documentInputIteration++;

        session()->flash('status', 'Verification document uploaded successfully.');

        $this->redirectRoute('landlord.documents', navigate: true);
    }

    public function upload(): void
    {
        $this->saveDocument();
    }

    public function render(): View
    {
        $documentsAvailable = $this->hasLandlordDocumentsTable();
        $profile = $this->landlordProfile();
        $documentUploadLimitKilobytes = $this->documentUploadLimitKilobytes();
        $documents = $documentsAvailable
            ? LandlordDocument::query()
                ->where('landlord_profile_id', $profile->getKey())
                ->with('reviewer')
                ->latest()
                ->get()
            : new Collection();

        return view('livewire.landlord.documents', [
            'profile' => $profile,
            'documentsAvailable' => $documentsAvailable,
            'documents' => $documents,
            'documentUploadLimitLabel' => UploadConfiguration::formatKilobytes($documentUploadLimitKilobytes),
            'documentUploadLimitBytes' => UploadConfiguration::kilobytesToBytes($documentUploadLimitKilobytes),
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Landlord Documents'));
    }

    protected function storeVerificationDocument(LandlordProfile $profile, string $documentType, mixed $uploadedFile): void
    {
        $path = $uploadedFile->store("landlord-documents/{$profile->id}", 'local');
        $fileSize = $this->resolveStoredDocumentSize($uploadedFile, $path);

        try {
            DB::transaction(function () use ($profile, $documentType, $uploadedFile, $path, $fileSize): void {
                LandlordDocument::create([
                    'landlord_profile_id' => $profile->id,
                    'document_type' => $documentType,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $uploadedFile->getMimeType() ?? $uploadedFile->getClientMimeType() ?? 'application/octet-stream',
                    'file_size' => $fileSize,
                    'review_status' => 'pending',
                ]);
            });
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($path);

            throw $throwable;
        }
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

    protected function hasLandlordDocumentsTable(): bool
    {
        return Schema::hasTable('landlord_documents');
    }

    protected function resolveStoredDocumentSize(mixed $uploadedFile, string $path): int
    {
        try {
            $size = Storage::disk('local')->size($path);

            if (is_numeric($size)) {
                return (int) $size;
            }
        } catch (Throwable) {
            // Fall back to the temporary upload metadata if the stored file size is unavailable.
        }

        try {
            $size = $uploadedFile->getSize();

            if (is_numeric($size)) {
                return (int) $size;
            }
        } catch (Throwable) {
            // Surface a clear persistence error to the caller.
        }

        throw new \RuntimeException('Unable to determine the stored landlord document size.');
    }

    protected function documentUploadLimitKilobytes(): int
    {
        return UploadConfiguration::effectiveMaxUploadKilobytes(5120);
    }

    protected function currentUser(): User
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if(! $user, 403);

        return $user;
    }
}
