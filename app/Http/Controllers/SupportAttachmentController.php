<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use App\Models\SupportRequestAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportAttachmentController extends Controller
{
    public function view(SupportRequest $supportRequest, SupportRequestAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($supportRequest, $attachment);

        return Storage::disk('local')->response($attachment->file_path, $this->safeFilename($attachment), [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(SupportRequest $supportRequest, SupportRequestAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($supportRequest, $attachment);

        return Storage::disk('local')->download($attachment->file_path, $this->safeFilename($attachment));
    }

    private function authorizeAttachment(SupportRequest $supportRequest, SupportRequestAttachment $attachment): void
    {
        abort_unless($supportRequest->user_id === auth()->id(), 404);
        abort_unless($attachment->support_request_id === $supportRequest->id, 404);
        if ($attachment->support_request_message_id !== null) {
            abort_unless($attachment->message()->where('is_internal', false)->exists(), 404);
        }
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);
    }

    private function safeFilename(SupportRequestAttachment $attachment): string
    {
        $filename = basename($attachment->original_name);
        $filename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'support-attachment';

        return Str::limit($filename, 180, '');
    }
}
