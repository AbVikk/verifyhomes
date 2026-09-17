<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantProfile;
use App\Support\AuditLogger;
use App\Support\WorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantVerificationController extends Controller
{
    public function download(TenantProfile $tenantProfile, string $document): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless(in_array($document, ['id', 'selfie'], true), 404);
        $path = $document === 'id' ? $tenantProfile->id_document_path : $tenantProfile->selfie_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        AuditLogger::log('tenant_verification_document_downloaded', auth()->user(), $tenantProfile, 'Downloaded tenant verification document.');

        return Storage::disk('local')->download($path, $document === 'id' ? 'identity-document' : 'selfie');
    }

    public function preview(TenantProfile $tenantProfile, string $document)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless(in_array($document, ['id', 'selfie'], true), 404);

        $path = $document === 'id' ? $tenantProfile->id_document_path : $tenantProfile->selfie_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $mimeType = Storage::disk('local')->mimeType($path);
        abort_unless(is_string($mimeType) && str_starts_with($mimeType, 'image/'), 404);

        AuditLogger::log('tenant_verification_document_previewed', auth()->user(), $tenantProfile, 'Previewed tenant verification image.');

        return Storage::disk('local')->response($path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function update(TenantProfile $tenantProfile): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $data = request()->validate([
            'status' => ['required', 'in:verified,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000'],
        ]);
        abort_unless($tenantProfile->verification_status === 'pending', 422);
        $verified = $data['status'] === 'verified';
        $tenantProfile->update([
            'verification_status' => $data['status'], 'verified_at' => $verified ? now() : null,
            'verified_by' => auth()->id(), 'admin_notes' => $data['admin_notes'] ?? null,
            'rejection_reason' => $verified ? null : $data['rejection_reason'],
        ]);
        AuditLogger::log('tenant_verification_'.$data['status'], auth()->user(), $tenantProfile, 'Reviewed tenant identity verification.');
        $notifier = app(WorkflowNotifier::class);
        $notifier->notify($tenantProfile->user, 'tenant-kyc-'.$data['status'].':'.$tenantProfile->id.':'.now()->timestamp, $verified ? 'Identity verified' : 'Identity verification needs attention', $verified ? 'Your identity has been verified. You can continue with your inspection/payment.' : 'Your identity verification needs attention. Review the reason and resubmit.', route('tenant.verification'), 'verification', 'View verification');

        return back()->with('status', $verified ? 'Tenant identity verified.' : 'Tenant verification rejected and returned for resubmission.');
    }
}
